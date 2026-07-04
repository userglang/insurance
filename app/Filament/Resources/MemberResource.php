<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Branch;
use App\Models\Member;
use App\Services\MemberStatusService;
use App\Services\StatusService;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'Member Management';
    protected static ?string $navigationLabel = 'Members';
    protected static ?string $pluralModelLabel = 'Members';
    protected static ?string $modelLabel = 'Member';

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        return Cache::remember(
            'member_navigation_badge_' . ($user?->id ?? 'guest'),
            300,
            function () use ($user) {

                $query = DB::table('members as m')
                    ->leftJoin('subscriptions as s', function ($join) {
                        $join->on('m.id', '=', 's.member_id')
                            ->whereRaw('s.id = (
                                SELECT id FROM subscriptions
                                WHERE member_id = m.id
                                ORDER BY expires_at DESC
                                LIMIT 1
                            )');
                    })
                    ->where('m.is_active', true)
                    ->where('m.status', 'accepted');

                // Restrict by branch (non-admin)
                if ($user && ! $user->hasRole('super_admin')) {
                    $branchNumber = $user->branch?->branch_number;

                    if (! $branchNumber) {
                        return null;
                    }

                    $query->where('m.branch_number', $branchNumber);
                }

                $counts = $query->selectRaw('
                    COUNT(CASE WHEN s.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
                    COUNT(CASE WHEN s.expires_at < NOW() THEN 1 END) as expired
                ')->first();

                $total = (int) ($counts->expiring_soon ?? 0)
                       + (int) ($counts->expired ?? 0);

                return $total > 0 ? (string) $total : null;
            }
        );
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() ? 'warning' : 'gray';
    }

    // -------------------------------------------------------------------------
    // Global Search
    // -------------------------------------------------------------------------

    public static function getGloballySearchableAttributes(): array
    {
        return ['cid', 'first_name', 'last_name', 'email'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getGlobalSearchEloquentQuery()
            ->select([
                'id',
                'cid',
                'first_name',
                'last_name',
                'birth_date',
                'branch_number',
                'is_active',
            ])
            ->where(function ($q) {
                $q->whereNull('remark')
                ->orWhere('remark', 'not like', '%Merged into:%');
            })
            ->with([
                'branch:id,branch_number,branch_name',
                'subscriptions' => fn ($q) => $q
                    ->select([
                        'id',
                        'member_id',
                        'expires_at',
                        'payment_date',
                        'amount',
                    ])
                    ->latest('expires_at') // or use 'expires_at' if preferred
                    ->limit(1),
            ])
            ->orderByDesc('is_active')
            ->orderBy('last_name')
            ->limit(5);

        // Restrict for non-admin
        if ($user && ! $user->hasRole('super_admin')) {
            $branchNumber = $user->branch?->branch_number;

            $query->where('is_active', true)
                ->when(
                    $branchNumber,
                    fn ($q) => $q->where('branch_number', $branchNumber),
                    fn ($q) => $q->whereNull('branch_number')
                );
        }

        return $query;
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->last_name}, {$record->first_name}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $subscription = $record->subscriptions->first();

        $expires = $subscription?->expires_at;
        $payment = $subscription?->payment_date;
        $amount  = $subscription?->amount;

        // Payment Date
        $paymentStatus = match (true) {
            ! $payment => 'No Payment Found',
            default => $payment->format('M j, Y'),
        };

        // Amount
        $amountStatus = match (true) {
            ! $amount => '0.00',
            default => '₱ ' . number_format($amount, 2),
        };

        // Expiration
        $expirationStatus = match (true) {
            ! $expires => 'No subscription',
            $expires->isPast() => $expires->format('M j, Y') . ' (Expired)',
            $expires->lt(now()->addDays(30)) => $expires->format('M j, Y') . ' (Expires soon)',
            default => $expires->format('M j, Y') . ' (Active)',
        };

        return [
            __('Age')          => $record->age ? $record->age . ' Years Old' : 'N/A',
            __('Last Payment') => $paymentStatus . ' ('. $amountStatus .')',
            __('Expires At')   => $expirationStatus,
            __('Status')       => $record->is_active ? 'Active' : 'Archived/Deceased',
            __('Branch')       => $record->branch?->branch_name ?? 'N/A',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make()
                    ->columnSpanFull()
                    ->schema([
                        Wizard\Step::make('Personal Information')
                            ->schema(static::getPersonalInformation()),
                        Wizard\Step::make('Contact Information')
                            ->schema(static::getContactInformation()),
                        Wizard\Step::make('Employment Information')
                            ->schema(static::getEmploymentInformation()),
                        Wizard\Step::make('Others')
                            ->schema([
                                ...static::getGovernmentIDs(),
                                ...static::getAdditionalInformation(),
                            ]),
                    ]),
            ])
            ->columns(1)
            ->statePath('data');
    }

    // -------------------------------------------------------------------------
    // Form Sections
    // -------------------------------------------------------------------------

    public static function getPersonalInformation(): array
    {
        $user = Auth::user();
        $isSuperAdmin = $user?->hasRole('super_admin');

        return [
            Forms\Components\Section::make('Personal Information')
                ->description('Basic personal details')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('cid')
                            ->label('CID')
                            ->maxLength(255)
                            ->helperText('Leave empty for auto-generation')
                            ->default(null)
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('branch_number')
                            ->label('Branch')
                            ->placeholder('Select a branch')
                            ->options(function () use ($user, $isSuperAdmin) {
                                if ($isSuperAdmin) {
                                    return Cache::remember('branches_options', 3600,
                                        fn () => Branch::pluck('branch_name', 'branch_number')
                                    );
                                }

                                if ($user?->branch->branch_number) {
                                    return Branch::where('branch_number', $user->branch->branch_number)
                                        ->pluck('branch_name', 'branch_number');
                                }

                                return [];
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Select the branch this client belongs to')
                            ->default(fn () => ! $isSuperAdmin ? $user?->branch->branch_number : null)
                            ->disabled(! $isSuperAdmin)
                            ->dehydrated(),
                    ]),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->placeholder('Enter first name')
                            ->required()
                            ->maxLength(100)
                            ->autocomplete('given-name'),

                        Forms\Components\TextInput::make('middle_name')
                            ->label('Middle Name')
                            ->placeholder('Enter middle name (optional)')
                            ->maxLength(100)
                            ->autocomplete('additional-name'),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->placeholder('Enter last name')
                            ->required()
                            ->maxLength(100)
                            ->autocomplete('family-name'),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('suffix')
                            ->label('Suffix')
                            ->placeholder('Jr., Sr., III, etc.')
                            ->maxLength(10)
                            ->autocomplete('honorific-suffix'),

                        Forms\Components\DatePicker::make('birth_date')
                            ->label('Date of Birth')
                            ->placeholder('Select birth date')
                            ->required()
                            ->displayFormat('F j, Y')
                            ->format('Y-m-d')
                            ->maxDate(now()->subYears(17))
                            ->minDate(now()->subYears(120))
                            ->beforeOrEqual('today')
                            ->helperText('Must be at least 18 years old'),
                    ]),

                    Forms\Components\Textarea::make('birth_place')
                        ->label('Place of Birth')
                        ->placeholder('Enter place of birth')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('gender')
                            ->label('Gender')
                            ->placeholder('Select gender')
                            ->options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'])
                            ->required(),

                        Forms\Components\Select::make('marital_status')
                            ->label('Marital Status')
                            ->placeholder('Select marital status')
                            ->options([
                                'Single'    => 'Single',
                                'Married'   => 'Married',
                                'Widowed'   => 'Widowed',
                                'Separated' => 'Separated',
                            ])
                            ->required(),
                    ]),
                ]),
        ];
    }

    public static function getContactInformation(): array
    {
        return [
            Forms\Components\Section::make('Contact Information')
                ->description('Email, phone, and address details')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->placeholder('Enter email address')
                            ->email()
                            ->maxLength(255)
                            ->suffixIcon('heroicon-m-envelope')
                            ->autocomplete('email')
                            ->unique(ignoreRecord: true)
                            ->helperText('We will use this for important notifications'),

                        Forms\Components\TextInput::make('contact_number')
                            ->label('Contact Number')
                            ->placeholder('0912 345 6789')
                            ->maxLength(20)
                            ->mask('9999 999 9999')
                            ->suffixIcon('heroicon-m-phone')
                            ->rule('regex:/^[\+]?[0-9\s\-\(\)]{7,20}$/'),
                    ]),

                    Forms\Components\Fieldset::make('Address')->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('house_number')
                                ->label('House/Unit Number')
                                ->placeholder('123, Unit 4B, etc.')
                                ->maxLength(50)
                                ->autocomplete('address-line1'),

                            Forms\Components\TextInput::make('street')
                                ->label('Street')
                                ->placeholder('Enter street name')
                                ->maxLength(255)
                                ->autocomplete('address-line2'),
                        ]),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('barangay')
                                ->label('Barangay')
                                ->placeholder('Enter barangay')
                                ->maxLength(255)
                                ->autocomplete('address-level4'),

                            Forms\Components\TextInput::make('city')
                                ->label('City/Municipality')
                                ->placeholder('Enter city or municipality')
                                ->required()
                                ->maxLength(255)
                                ->autocomplete('address-level2'),
                        ]),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('province')
                                ->label('Province')
                                ->placeholder('Enter province')
                                ->required()
                                ->maxLength(255)
                                ->autocomplete('address-level1'),

                            Forms\Components\TextInput::make('zipcode')
                                ->label('ZIP Code')
                                ->placeholder('1234')
                                ->maxLength(10)
                                ->numeric()
                                ->rule('digits_between:4,10')
                                ->autocomplete('postal-code'),
                        ]),
                    ]),
                ]),
        ];
    }

    public static function getEmploymentInformation(): array
    {
        return [
            Forms\Components\Section::make('Employment Information')
                ->description('Work and employment details')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('occupation')
                            ->label('Occupation')
                            ->placeholder('Enter occupation')
                            ->maxLength(255)
                            ->autocomplete('organization-title'),

                        Forms\Components\Select::make('employment_status')
                            ->label('Employment Status')
                            ->placeholder('Select employment status')
                            ->options([
                                'employed'      => 'Employed',
                                'self_employed' => 'Self-Employed',
                                'unemployed'    => 'Unemployed',
                                'student'       => 'Student',
                                'retired'       => 'Retired',
                                'other'         => 'Other',
                            ]),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name_of_employer')
                            ->label('Employer Name')
                            ->placeholder('Enter employer name')
                            ->maxLength(255)
                            ->autocomplete('organization'),

                        Forms\Components\TextInput::make('office_contact_number')
                            ->label('Office Contact Number')
                            ->placeholder('0912 345 6789')
                            ->maxLength(20)
                            ->mask('9999 999 9999')
                            ->rule('regex:/^[\+]?[0-9\s\-\(\)]{7,20}$/')
                            ->suffixIcon('heroicon-m-building-office'),
                    ]),

                    Forms\Components\Textarea::make('office_address')
                        ->label('Office Address')
                        ->placeholder('Enter complete office address')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getGovernmentIDs(): array
    {
        return [
            Forms\Components\Section::make('Government IDs')
                ->description('Social Security and Tax identification numbers')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('sss_gsis')
                            ->label('SSS/GSIS Number')
                            ->placeholder('12-3456789-0')
                            ->maxLength(15)
                            ->rule('regex:/^\d{2}-\d{7}-\d{1}$/')
                            ->mask('99-9999999-9')
                            ->helperText('Format: XX-XXXXXXX-X'),

                        Forms\Components\TextInput::make('tin')
                            ->label('TIN Number')
                            ->placeholder('123-456-789')
                            ->maxLength(20)
                            ->mask('999-999-999-999')
                            ->helperText('Format: XXX-XXX-XXX'),
                    ]),
                ]),
        ];
    }

    public static function getAdditionalInformation(): array
    {
        return [
            Forms\Components\Section::make('Additional Information')
                ->description('Status and additional notes')
                ->schema([
                    Forms\Components\RichEditor::make('remark')
                        ->label('Remarks')
                        ->placeholder('Enter any additional notes or remarks about this client')
                        ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList'])
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ];
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cid')
                    ->label('CID')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Unknown')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Member Name')
                    ->searchable(query: fn ($query, $search) => $query->where(
                        fn ($q) => $q
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                    ))
                    ->description(fn ($record) => '🏢 ' . ($record->branch?->branch_name ?? 'No branch'))
                    ->copyable()
                    ->copyMessage('Name copied!'),

                Tables\Columns\TextColumn::make('subscriptions.expires_at')
                    ->label('Expiration')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $record->is_active) {
                            return 'Archived / Deceased';
                        }

                        return $record->subscriptions
                            ->sortByDesc('expires_at')
                            ->first()
                            ?->expires_at
                            ?->format('M j, Y') ?? 'No subscription';
                    })
                    ->description(function ($record) {
                        if (! $record->is_active) return 'Member not active';

                        $expires = $record->subscriptions->sortByDesc('expires_at')->first()?->expires_at;

                        return match (true) {
                            ! $expires                       => 'No subscription',
                            $expires->lt(now())              => 'Expired',
                            $expires->lt(now()->addDays(30)) => 'Expires soon',
                            default                          => 'Active',
                        };
                    })
                    ->color(function ($state, $record) {
                        if (! $record->is_active) return 'gray';

                        $expires = $record->subscriptions->sortByDesc('expires_at')->first()?->expires_at;

                        return match (true) {
                            ! $expires                       => 'gray',
                            $expires->lt(now())              => 'danger',
                            $expires->lt(now()->addDays(30)) => 'warning',
                            default                          => 'success',
                        };
                    }),

                Tables\Columns\TextColumn::make('birth_date')
                    ->label('Birth Date')
                    ->date('M d, Y')
                    ->description(fn ($record) => $record->age
                        ? "{$record->age} year" . ($record->age > 1 ? 's' : '') . ' old'
                        : null
                    ),

                Tables\Columns\TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->color(fn ($state) => match (strtolower($state ?? '')) {
                        'male'   => 'blue',
                        'female' => 'pink',
                        default  => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('full_address')
                    ->label('Address')
                    ->searchable(['city', 'province', 'barangay'])
                    ->limit(30)
                    ->wrap()
                    ->tooltip(fn ($record) => $record->full_address)
                    ->description(fn ($record) => $record->contact_number
                        ? '📱 ' . $record->contact_number
                        : ($record->email ? '✉️ ' . $record->email : null)
                    ),

                Tables\Columns\TextColumn::make('occupation')
                    ->label('Occupation')
                    ->badge()
                    ->color('indigo')
                    ->searchable()
                    ->placeholder('Not specified')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('employment_status')
                    ->label('Employment')
                    ->badge()
                    ->color(fn ($state) => match (strtolower($state ?? '')) {
                        'employed'      => 'success',
                        'self_employed' => 'info',
                        'unemployed'    => 'warning',
                        'retired'       => 'secondary',
                        'student'       => 'primary',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', strtolower($state ?? ''))))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            // -----------------------------------------------------------------
            // Filters
            // -----------------------------------------------------------------
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([1 => 'Active', 0 => 'Archived/Deceased'])
                    ->default(1)
                    ->placeholder('All Statuses'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Application Status')
                    ->options([
                        'pending'  => 'Pending',
                        'accepted' => 'Accepted',
                        'declined' => 'Declined',
                    ])
                    ->default('accepted')
                    ->placeholder('All Applications'),

                Tables\Filters\SelectFilter::make('gender')
                    ->label('Gender')
                    ->options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'])
                    ->placeholder('All Genders'),

                Tables\Filters\Filter::make('subscription_status')
                    ->label('Subscription Status')
                    ->form([
                        Forms\Components\Select::make('subscription_filter')
                            ->label('Filter by')
                            ->options([
                                'active'          => 'Active Subscriptions',
                                'expired'         => 'Expired Subscriptions',
                                'expiring_soon'   => 'Expiring Soon',
                                'no_subscription' => 'No Subscription',
                            ])
                            ->placeholder('All Subscription Statuses'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['subscription_filter'] ?? null) {
                            'active'          => $query->whereRaw('(SELECT expires_at FROM subscriptions WHERE member_id = members.id ORDER BY expires_at DESC LIMIT 1) > NOW()'),
                            'expired'         => $query->whereRaw('(SELECT expires_at FROM subscriptions WHERE member_id = members.id ORDER BY expires_at DESC LIMIT 1) < NOW()'),
                            'expiring_soon'   => $query->whereRaw('(SELECT expires_at FROM subscriptions WHERE member_id = members.id ORDER BY expires_at DESC LIMIT 1) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)'),
                            'no_subscription' => $query->whereDoesntHave('subscriptions'),
                            default           => $query,
                        };
                    }),


                Tables\Filters\SelectFilter::make('branch_number')
                    ->label('Branch')
                    ->options(function () {
                        $user = Auth::user();

                        if ($user?->hasRole('super_admin')) {
                            return Cache::remember('branch_filter_options', 3600,
                                fn () => Branch::pluck('branch_name', 'branch_number')
                            );
                        }

                        if ($user?->branch_number) {
                            return Branch::where('branch_number', $user->branch_number)
                                ->pluck('branch_name', 'branch_number');
                        }

                        return [];
                    })
                    ->searchable()
                    ->columnSpanFull()
                    ->preload()
                    ->placeholder('All Branches')
                    ->visible(fn () => Auth::user()?->hasRole('super_admin')),

                Tables\Filters\Filter::make('expiration_date_range')
                    ->label('Expiration Date Range')
                    ->columnSpanFull()
                    ->form([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Filter by expiration date')
                            ->live()
                            ->default(true),

                        Forms\Components\DatePicker::make('expires_from')
                            ->label('Expires From')
                            ->placeholder('Select start date')
                            ->default(now()->subMonth()->startOfMonth())
                            ->live()
                            ->disabled(fn (Forms\Get $get) => ! $get('enabled'))
                            ->maxDate(fn (Forms\Get $get) => $get('expires_until') ?: null),

                        Forms\Components\DatePicker::make('expires_until')
                            ->label('Expires Until')
                            ->placeholder('Select end date')
                            ->default(now()->subMonth()->endOfMonth())
                            ->live()
                            ->disabled(fn (Forms\Get $get) => ! $get('enabled'))
                            ->minDate(fn (Forms\Get $get) => $get('expires_from') ?: null),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['enabled'])) {
                            return $query;
                        }

                        $from  = $data['expires_from']  ?? null;
                        $until = $data['expires_until'] ?? null;

                        if (! $from && ! $until) {
                            return $query;
                        }

                        return $query
                            ->when($from, fn (Builder $q) => $q->whereRaw(
                                '(SELECT expires_at FROM subscriptions WHERE member_id = members.id ORDER BY expires_at DESC LIMIT 1) >= ?',
                                [$from]
                            ))
                            ->when($until, fn (Builder $q) => $q->whereRaw(
                                '(SELECT expires_at FROM subscriptions WHERE member_id = members.id ORDER BY expires_at DESC LIMIT 1) <= ?',
                                [$until]
                            ));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['enabled'])) {
                            return null;
                        }

                        $from  = $data['expires_from']  ?? null;
                        $until = $data['expires_until'] ?? null;

                        if (! $from && ! $until) {
                            return null;
                        }

                        return 'Expires: ' . ($from ?: '…') . ' to ' . ($until ?: '…');
                    }),
            ])

            // -----------------------------------------------------------------
            // Row Actions
            // -----------------------------------------------------------------
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View')
                        ->icon('heroicon-m-eye')
                        ->color('info'),

                    Tables\Actions\Action::make('toggle_status')
                        ->label(fn (Model $record) => Auth::user()->hasRole('super_admin')
                            ? ($record->is_active ? 'Deactivate' : 'Activate')
                            : 'Deactivate'
                        )
                        ->tooltip(fn (Model $record) => Auth::user()->hasRole('super_admin')
                            ? ($record->is_active ? 'Click to deactivate or mark as deceased this record' : 'Click to activate this record')
                            : 'Click to deactivate or mark as deceased this record'
                        )
                        ->icon(fn (Model $record) => Auth::user()->hasRole('super_admin')
                            ? ($record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                            : 'heroicon-o-x-circle'
                        )
                        ->color(fn (Model $record) => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(fn (Model $record) => app(StatusService::class)->toggle($record, Auth::user()))
                        ->visible(fn (Model $record) => $record->is_active || Auth::user()->hasRole('super_admin')),

                    Tables\Actions\Action::make('accept')
                        ->label('Accept')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Model $record) => $record->is_active && in_array($record->status, ['pending', 'declined']))
                        ->action(fn (Model $record) => app(MemberStatusService::class)->accept($record)),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray'),
            ])

            // -----------------------------------------------------------------
            // Bulk Actions
            // -----------------------------------------------------------------
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Members')
                        ->modalDescription('Are you sure you want to delete these members? This action cannot be undone.')
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('Members Deleted')
                                ->body('Selected members have been deleted successfully.')
                        ),

                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activate Selected Members')
                        ->modalDescription('Are you sure you want to activate all selected members?')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->successNotification(
                            Notification::make()->success()->title('Members Activated')
                                ->body('Selected members have been activated successfully.')
                        )
                        ->visible(fn () => Auth::user()?->hasRole('super_admin')),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate or Mark as Deceased Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Selected Members')
                        ->modalDescription('Are you sure you want to deactivate all selected members?')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->successNotification(
                            Notification::make()->success()->title('Members Deactivated')
                                ->body('Selected members have been deactivated successfully.')
                        ),

                    Tables\Actions\BulkAction::make('bulk_accept')
                        ->label('Accept Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Accept Members')
                        ->modalDescription('Are you sure you want to accept all valid selected members?')
                        ->modalButton('Accept')
                        ->action(function (Collection $records) {
                            $records
                                ->filter(fn ($r) => $r->is_active && in_array($r->status, ['pending', 'declined']))
                                ->each->update(['status' => 'accepted']);
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotification(
                            Notification::make()->success()->title('Members Accepted')
                                ->body('Selected members were accepted successfully.')
                        ),

                    Tables\Actions\BulkAction::make('bulk_decline')
                        ->label('Decline Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Decline Members')
                        ->modalDescription('Are you sure you want to decline all valid selected members?')
                        ->modalButton('Decline')
                        ->action(function (Collection $records) {
                            $records
                                ->filter(fn ($r) => $r->is_active)
                                ->each->update(['status' => 'declined']);
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotification(
                            Notification::make()->success()->title('Members Declined')
                                ->body('Selected members were declined successfully.')
                        ),

                    Tables\Actions\BulkAction::make('export')
                        ->label('Export Selected')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function (Collection $records) {

                            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                            $sheet       = $spreadsheet->getActiveSheet();

                            $headers = [
                                'ID', 'CID', 'Name', 'Branch', 'Email', 'Phone', 'Address',
                                'Age', 'Birth Date', 'Gender', 'Marital Status', 'Occupation', 'Employment Status',
                                'Status', 'Joined Date', 'Account Name', 'Account Number', 'Amount',
                                'Payment Date', 'Subscription Date', 'Remarks', 'Note: Date Format',
                            ];

                            $sheet->fromArray($headers, null, 'A1');

                            // ✅ Highlight ONLY selected headers (light green)
                            $highlightColumns = ['P', 'Q', 'R', 'S', 'T', 'U'];

                            foreach ($highlightColumns as $col) {
                                $sheet->getStyle("{$col}1")
                                    ->getFill()
                                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                    ->getStartColor()
                                    ->setARGB('FFCCFFCC'); // light green

                                $sheet->getStyle("{$col}1")
                                    ->getFont()
                                    ->setBold(true);
                            }

                            $totalColumns = count($headers);
                            $totalRows    = $records->count() + 1;

                            foreach (range(1, $totalColumns) as $colIndex) {
                                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

                                // Skip Amount column (index 18 = column R)
                                if ($colIndex === 18) continue;

                                $sheet->getStyle("{$colLetter}1:{$colLetter}{$totalRows}")
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
                            }

                            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumns);

                            $row = 2;
                            foreach ($records->sortBy('last_name') as $record) {
                                $sub = $record->latestSubscription;

                                $rowData = [
                                    (string) $record->id,
                                    (string) $record->cid,
                                    (string) $record->full_name,
                                    (string) ($record->branch?->branch_name ?? 'N/A'),
                                    (string) $record->email,
                                    (string) $record->contact_number,
                                    (string) $record->full_address,
                                    (string) $record->age,
                                    (string) $record->birth_date,
                                    (string) $record->gender_label,
                                    (string) $record->marital_status_label,
                                    (string) $record->occupation,
                                    (string) $record->employment_status,
                                    (string) ($record->is_active ? 'Active' : 'Archived'),
                                    (string) $record->created_at->format('m/d/Y'),
                                    (string) ($sub?->productAccount?->product_name ?? ''),
                                    (string) ($sub?->productAccount?->account_number ?? ''),
                                    $sub?->amount ?? 0,
                                    '',
                                    (string) ($sub?->expires_at?->format('m/d/Y') ?? ''),
                                    'RENEWAL',
                                    'month/day/Year (12/18/2025)',
                                ];

                                foreach ($rowData as $colIndex => $value) {
                                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);

                                    if ($colIndex === 17) {
                                        $sheet->setCellValue("{$colLetter}{$row}", $value);
                                        $sheet->getStyle("{$colLetter}{$row}")
                                            ->getNumberFormat()
                                            ->setFormatCode('#,##0.00');
                                    } else {
                                        $sheet->setCellValueExplicit(
                                            "{$colLetter}{$row}",
                                            $value,
                                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                                        );
                                    }
                                }

                                // Highlight row based on age
                                $age = (int) $record->age;

                                if ($age >= 70) {
                                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                                        ->getFill()
                                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                        ->getStartColor()
                                        ->setARGB('FFFF0000');

                                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                                        ->getFont()->getColor()->setARGB('FFFFFFFF');

                                } elseif ($age >= 65) {
                                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                                        ->getFill()
                                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                        ->getStartColor()
                                        ->setARGB('FFFF6600');

                                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                                        ->getFont()->getColor()->setARGB('FFFFFFFF');
                                }

                                // Highlight Amount if invalid
                                $amount       = (float) ($sub?->amount ?? 0);
                                $validAmounts = [180, 360];

                                if (! in_array($amount, $validAmounts)) {
                                    $sheet->getStyle("R{$row}")
                                        ->getFill()
                                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                        ->getStartColor()
                                        ->setARGB('FFFFFF00');

                                    $sheet->getStyle("R{$row}")
                                        ->getFont()->getColor()->setARGB('FF000000');
                                }

                                $row++;
                            }

                            // SUM row
                            $sumRow = $row;

                            $sheet->setCellValueExplicit(
                                "A{$sumRow}",
                                'TOTAL',
                                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                            );

                            $sheet->setCellValue("R{$sumRow}", "=SUM(R2:R" . ($sumRow - 1) . ")");

                            $sheet->getStyle("R{$sumRow}")
                                ->getNumberFormat()
                                ->setFormatCode('#,##0.00');

                            $sheet->getStyle("A{$sumRow}:R{$sumRow}")
                                ->getFont()
                                ->setBold(true);

                            $filename = 'pre-need_export-' . now()->format('Y-m-d-H-i-s') . '.xlsx';
                            $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

                            return response()->streamDownload(function () use ($writer) {
                                $writer->save('php://output');
                            }, $filename, [
                                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ]);
                        }),

                    Tables\Actions\BulkAction::make('mergeMembers')
                        ->label('Merge Members')
                        ->icon('heroicon-o-user-group')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            if ($records->count() < 2) {
                                Notification::make()
                                    ->title('Please select at least two members to merge.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $primary    = $records->first();
                            $duplicates = $records->where('id', '!=', $primary->id);

                            $primary->loadMissing('subscriptions', 'productAccounts');

                            foreach ($duplicates as $duplicate) {
                                $duplicate->loadMissing('subscriptions', 'productAccounts');

                                $duplicate->subscriptions->each(fn ($s) => $s->update(['member_id' => $primary->id]));
                                $duplicate->productAccounts->each(fn ($a) => $a->update(['member_id' => $primary->id]));

                                $duplicate->update([
                                    'is_active' => false,
                                    'remark'    => 'Merged into: ' . $primary->full_name,
                                ]);
                            }

                            Notification::make()->title('Members merged successfully.')->success()->send();
                        }),
                ]),
            ])

            // -----------------------------------------------------------------
            // Table Options
            // -----------------------------------------------------------------
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->searchOnBlur()
            ->searchDebounce('750ms')
            ->poll(null)
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->extremePaginationLinks()
            ->emptyStateHeading('No members found')
            ->emptyStateDescription('Get started by adding your first member.')
            ->emptyStateIcon('heroicon-o-users')
            ->filtersFormColumns(2);
    }

    // -------------------------------------------------------------------------
    // Relations & Pages
    // -------------------------------------------------------------------------

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit'   => Pages\EditMember::route('/{record}/edit'),
            'view'   => Pages\ViewMember::route('/{record}/view'),
        ];
    }
}
