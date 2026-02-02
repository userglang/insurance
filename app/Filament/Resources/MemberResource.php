<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Branch;
use App\Models\Member;
use App\Jobs\ProcessBulkMemberOperation;
use App\Services\MemberExportService;
use App\Services\MemberStatusService;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;
use App\Services\StatusService;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'Member Management';
    protected static ?string $navigationLabel = 'Members';
    protected static ?string $pluralModelLabel = 'Members';
    protected static ?string $modelLabel = 'Member';

    public static function getNavigationBadge(): ?string
    {
        // Cache expensive badge calculations for 5 minutes
        return Cache::remember('member_navigation_badge_' . Auth::id(), 300, function () {
            $query = DB::table('members as m')
                ->leftJoin('subscriptions as s', function ($join) {
                    $join->on('m.id', '=', 's.member_id')
                        ->whereRaw('s.id = (SELECT id FROM subscriptions WHERE member_id = m.id ORDER BY expires_at DESC LIMIT 1)');
                })
                ->where('m.is_active', true)
                ->where('m.status', 'accepted');

            // Apply role-based filtering for navigation badge
            $user = Auth::user();
            if ($user && !$user->hasRole('super_admin')) {
                if ($user->branch->branch_number) {
                    $query->where('m.branch_number', $user->branch->branch_number);
                } else {
                    // If user has no branch assigned and isn't super admin, show 0
                    return null;
                }
            }

            $counts = $query->selectRaw('
                COUNT(CASE WHEN s.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
                COUNT(CASE WHEN s.expires_at < NOW() THEN 1 END) as expired
            ')->first();

            $total = ($counts->expiring_soon ?? 0) + ($counts->expired ?? 0);
            return $total > 0 ? (string) $total : null;
        });
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getNavigationBadge() ? 'warning' : 'gray';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['cid', 'first_name', 'last_name', 'email'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        $query = parent::getGlobalSearchEloquentQuery()
            // Select only necessary columns for performance
            ->select(['id', 'cid', 'first_name', 'last_name', 'branch_number', 'is_active'])

            // Order by is_active so that the most active members are on top
            ->orderByDesc('is_active', `last_name`)

            // Eager load only necessary relations, avoid large or unneeded data
            ->with([
                'branch:id,branch_number,branch_name',
                'subscriptions' => function ($query) {
                    $query->select(['id', 'member_id', 'expires_at'])
                        ->orderByDesc('expires_at') // Most recent first
                        ->limit(1);  // We only need the latest subscription
                }
            ]);

        // Fetch the authenticated user to apply role-based filtering
        $user = Auth::user();
        if ($user) {
            // If the user is not a super admin, apply branch filtering
            if (!$user->hasRole('super_admin')) {
                if ($user->branch && $user->branch->branch_number) {
                    // Filter records based on the user's branch number
                    $query->where('branch_number', $user->branch->branch_number);
                } else {
                    // If no branch is assigned, prevent showing results
                    $query->whereNull('branch_number');
                }

                // Apply 'is_active' filter only for non-super admins
                $query->where('is_active', true);
            }
        }

        // Limit the query to 5 records, but return whatever is available (fewer than 5 is fine)
        return $query->limit(1);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->last_name . ', ' . $record->first_name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        // Get the latest subscription, if exists
        $latestSubscription = $record->subscriptions->first();
        $expiresAt = $latestSubscription?->expires_at;

        // Determine the subscription expiration status
        $expirationStatus = 'No subscription';
        if ($expiresAt) {
            if ($expiresAt->isPast()) {
                $expirationStatus = $expiresAt->format('M j, Y') . ' (Expired)';
            } elseif ($expiresAt->lt(now()->addDays(30))) {
                $expirationStatus = $expiresAt->format('M j, Y') . ' (Expires soon)';
            } else {
                $expirationStatus = $expiresAt->format('M j, Y') . ' (Active)';
            }
        }

        // Return key-value pair for the record details to be shown in search results
        return [
            __('Branch') => $record->branch?->branch_name ?? 'N/A',
            __('Expires At') => $expirationStatus,
            __('Status') => $record->is_active ? 'Active' : 'Archived/Deceased',
        ];
    }


    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make()
                    ->columnSpanFull()
                    ->schema([
                        Wizard\Step::make('Personal Information')
                            ->schema(self::getPersonalInformation()),
                        Wizard\Step::make('Contact Information')
                            ->schema(self::getContactInformation()),
                        Wizard\Step::make('Employment Information')
                            ->schema(self::getEmploymentInformation()),
                        Wizard\Step::make('Others')
                            ->schema([
                                ...self::getGovernmentIDs(),
                                ...self::getAdditionalInformation()
                            ]),
                    ]),
            ])
            ->columns(1)
            ->statePath('data');
    }

    public static function getPersonalInformation(): array
    {
        return [
            Forms\Components\Section::make('Personal Information')
                ->description('Basic personal details')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('cid')
                                ->label('CID')
                                ->maxLength(255)
                                ->helperText('Leave empty for auto-generation')
                                ->default(null)
                                ->unique(ignoreRecord: true),

                            Forms\Components\Select::make('branch_number')
                                ->label('Branch')
                                ->placeholder('Select a branch')
                                ->options(function () {
                                    $user = Auth::user();

                                    // For super_admin, show all branches
                                    if ($user && $user->hasRole('super_admin')) {
                                        return Cache::remember('branches_options', 3600,
                                            fn () => Branch::pluck('branch_name', 'branch_number')
                                        );
                                    }

                                    // For other roles, show only their assigned branch
                                    if ($user && $user->branch->branch_number) {
                                        return Branch::where('branch_number', $user->branch->branch_number)
                                            ->pluck('branch_name', 'branch_number');
                                    }

                                    return [];
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Select the branch this client belongs to')
                                ->default(function () {
                                    $user = Auth::user();
                                    // Auto-select user's branch if they're not super admin
                                    if ($user && !$user->hasRole('super_admin') && $user->branch->branch_number) {
                                        return $user->branch->branch_number;
                                    }
                                    return null;
                                })
                                ->disabled(function () {
                                    $user = Auth::user();
                                    // Disable field for non-super admins (auto-select their branch)
                                    return $user && !$user->hasRole('super_admin');
                                }),
                        ]),

                    Forms\Components\Grid::make(3)
                        ->schema([
                            Forms\Components\TextInput::make('first_name')
                                ->label('First Name')
                                ->placeholder('Enter first name')
                                ->required()
                                ->maxLength(100)
                                ->rule('regex:/^[a-zA-Z\s\-\.\']+$/')
                                ->autocomplete('given-name'),

                            Forms\Components\TextInput::make('middle_name')
                                ->label('Middle Name')
                                ->placeholder('Enter middle name (optional)')
                                ->maxLength(100)
                                ->rule('regex:/^[a-zA-Z\s\-\.\']*$/')
                                ->autocomplete('additional-name'),

                            Forms\Components\TextInput::make('last_name')
                                ->label('Last Name')
                                ->placeholder('Enter last name')
                                ->required()
                                ->maxLength(100)
                                ->rule('regex:/^[a-zA-Z\s\-\.\']+$/')
                                ->autocomplete('family-name'),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
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
                                ->maxDate(now()->subYears(18))
                                ->minDate(now()->subYears(100))
                                ->beforeOrEqual('today')
                                ->helperText('Must be at least 18 years old'),
                        ]),

                    Forms\Components\Textarea::make('birth_place')
                        ->label('Place of Birth')
                        ->placeholder('Enter place of birth')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('gender')
                                ->label('Gender')
                                ->placeholder('Select gender')
                                ->options([
                                    'Male' => 'Male',
                                    'Female' => 'Female',
                                    'Other' => 'Other',
                                ])
                                ->required(),

                            Forms\Components\Select::make('marital_status')
                                ->label('Marital Status')
                                ->placeholder('Select marital status')
                                ->options([
                                    'Single' => 'Single',
                                    'Married' => 'Married',
                                    'Widowed' => 'Widowed',
                                    'Separated' => 'Separated',
                                    'Divorced' => 'Divorced',
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
                    Forms\Components\Grid::make(2)
                        ->schema([
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
                                ->rule('regex:/^[\+]?[0-9\s\-\(\)]{7,20}$/')
                                ->helperText('Include country code if international'),
                        ]),

                    Forms\Components\Fieldset::make('Address')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
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

                            Forms\Components\Grid::make(2)
                                ->schema([
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

                            Forms\Components\Grid::make(2)
                                ->schema([
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
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('occupation')
                                ->label('Occupation')
                                ->placeholder('Enter occupation')
                                ->maxLength(255)
                                ->autocomplete('organization-title'),

                            Forms\Components\Select::make('employment_status')
                                ->label('Employment Status')
                                ->placeholder('Select employment status')
                                ->options([
                                    'employed' => 'Employed',
                                    'self_employed' => 'Self-Employed',
                                    'unemployed' => 'Unemployed',
                                    'student' => 'Student',
                                    'retired' => 'Retired',
                                    'other' => 'Other',
                                ]),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
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
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('sss_gsis')
                                ->label('SSS/GSIS Number')
                                ->placeholder('12-3456789-0')
                                ->maxLength(15)
                                ->rule('regex:/^\d{2}-\d{7}-\d{1}$/')
                                ->mask('99-9999999-9')
                                ->helperText('Format: XX-XXXXXXX-X'),

                            Forms\Components\TextInput::make('tin')
                                ->label('TIN Number')
                                ->placeholder('123-456-789-000')
                                ->maxLength(15)
                                ->rule('regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/')
                                ->mask('999-999-999-999')
                                ->helperText('Format: XXX-XXX-XXX-XXX'),
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
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'bulletList',
                            'orderedList',
                        ])
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ];
    }


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
                    ->placeholder('Unknown'),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Member Name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->limit(30),

                Tables\Columns\TextColumn::make('subscriptions.expires_at')
                    ->label('Expiration Date')
                    ->date('M j, Y')
                    ->placeholder('Archived/Deceased')
                    ->getStateUsing(function ($record) {
                        // If member is inactive, show "Archived/Deceased" instead of expiration date
                        if (!$record->is_active) {
                            return 'Archived/Deceased';
                        }

                        // For active members, return the expiration date
                        return $record->subscriptions->sortByDesc('expires_at')->first()?->expires_at;
                    })
                    ->color(function ($state, $record) {
                        // If member is inactive, use gray color
                        if (!$record->is_active) {
                            return 'gray';
                        }

                        // For active members, use existing color logic
                        return $state instanceof \Illuminate\Support\Carbon && $state->lt(now())
                            ? 'danger'
                            : ($state && $state->lt(now()->addDays(30))
                            ? 'warning'
                            : 'success');
                    })
                    ->description(function ($record) {
                        // If member is inactive, show "Member not active"
                        if (!$record->is_active) {
                            return 'Member not active';
                        }

                        // For active members, use existing description logic
                        $expiresAt = $record->subscriptions->sortByDesc('expires_at')->first()?->expires_at;

                        if (! $expiresAt instanceof \Illuminate\Support\Carbon) {
                            return 'No subscription';
                        }

                        return $expiresAt->lt(now())
                            ? 'Expired'
                            : ($expiresAt->lt(now()->addDays(30)) ? 'Expires soon' : 'Active');
                    })
                    ->formatStateUsing(function ($state, $record) {
                        // If member is inactive, return the "Archived/Deceased" text as-is
                        if (!$record->is_active) {
                            return $state;
                        }

                        // For active members, format the date normally
                        return $state instanceof \Illuminate\Support\Carbon ? $state->format('M j, Y') : $state;
                    }),

                Tables\Columns\TextColumn::make('age')
                    ->label('Age')
                    ->suffix(' years')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('branch.branch_name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-building-office-2'),

                Tables\Columns\TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'male' => 'blue',
                        'female' => 'pink',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied')
                    ->icon('heroicon-m-envelope')
                    ->color('blue')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn ($record): string => $record->email ?? '')
                    ->description(fn ($record) =>
                    ($record->contact_number && $record->contact_number != 0)
                        ? $record->contact_number
                        : null
                    )
                    ->limit(25),

                Tables\Columns\TextColumn::make('full_address')
                    ->label('Address')
                    ->searchable(['city', 'province', 'barangay'])
                    ->limit(30)
                    ->tooltip(fn (Model $record): string => $record->full_address)
                    ->icon('heroicon-m-map-pin')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('occupation')
                    ->label('Occupation')
                    ->searchable()
                    ->badge()
                    ->color('indigo')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not specified'),


                Tables\Columns\TextColumn::make('employment_status')
                    ->label('Employment')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'employed' => 'success',
                        'self_employed' => 'info',
                        'unemployed' => 'warning',
                        'retired' => 'secondary',
                        'student' => 'primary',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (string $state): string =>
                    str_replace('_', ' ', ucwords($state))
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Archived/Deceased',
                    ])
                    ->default(1)
                    ->placeholder('All Statuses'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Application Status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'declined' => 'Declined',
                    ])
                    ->default('accepted')
                    ->placeholder('All Applications'),

                Tables\Filters\SelectFilter::make('branch_number')
                    ->label('Branch')
                    ->options(function () {
                        $user = Auth::user();

                        // For super_admin, show all branches
                        if ($user && $user->hasRole('super_admin')) {
                            return Cache::remember('branch_filter_options', 3600,
                                fn () => Branch::pluck('branch_name', 'branch_number')
                            );
                        }

                        // For other roles, show only their assigned branch
                        if ($user && $user->branch_number) {
                            return Branch::where('branch_number', $user->branch_number)
                                ->pluck('branch_name', 'branch_number');
                        }

                        return [];
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('All Branches')
                    ->visible(fn () => Auth::user()?->hasRole('super_admin')),

                Tables\Filters\SelectFilter::make('gender')
                    ->label('Gender')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                        'Other' => 'Other',
                    ])
                    ->placeholder('All Genders'),

                Tables\Filters\Filter::make('subscription_status')
                    ->label('Subscription Status')
                    ->form([
                        Forms\Components\Select::make('subscription_filter')
                            ->label('Filter by')
                            ->options([
                                'active' => 'Active Subscriptions',
                                'expired' => 'Expired Subscriptions',
                                'expiring_soon' => 'Expiring Soon',
                                'no_subscription' => 'No Subscription',
                            ])
                            ->placeholder('All Subscription Statuses')
                            // ->default('expiring_soon') // Set default here
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['subscription_filter'])) {;
                            return $query;
                        }

                        return match ($data['subscription_filter']) {
                            'active' => $query->whereRaw('(SELECT expires_at FROM subscriptions WHERE member_id = members.id ORDER BY expires_at DESC LIMIT 1) > NOW()'),
                            'expired' => $query->whereRaw('(SELECT expires_at FROM subscriptions WHERE member_id = members.id ORDER BY expires_at DESC LIMIT 1) < NOW()'),
                            'expiring_soon' => $query->whereRaw('(SELECT expires_at FROM subscriptions WHERE member_id = members.id ORDER BY expires_at DESC LIMIT 1) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)'),
                            'no_subscription' => $query->whereDoesntHave('subscriptions'),
                            default => $query
                        };
                    }),

                    Tables\Filters\Filter::make('expiration_date_range')
                        ->label('Expiration Date Range')
                        ->form([
                            Forms\Components\DatePicker::make('expires_from')
                                ->label('Expires From')
                                ->placeholder('Select start date')
                                ->default(now()->subMonth()->startOfMonth()), // 1 month before today
                            Forms\Components\DatePicker::make('expires_until')
                                ->label('Expires Until')
                                ->placeholder('Select end date')
                                ->default(now()->addMonth()->endOfMonth()), // 1 month after today
                        ])
                        ->query(function (Builder $query, array $data): Builder {
                            $expiresFrom = $data['expires_from'] ?? now()->subMonth()->startOfMonth(); // Default to 1 month before
                            $expiresUntil = $data['expires_until'] ?? now()->addMonth()->endOfMonth(); // Default to 1 month after

                            // Only include members with subscriptions within the date range
                            return $query
                                ->whereRaw(
                                    '(SELECT expires_at FROM subscriptions WHERE member_id = members.id ORDER BY expires_at DESC LIMIT 1) >= ?',
                                    [$expiresFrom]
                                )
                                ->whereRaw(
                                    '(SELECT expires_at FROM subscriptions WHERE member_id = members.id ORDER BY expires_at DESC LIMIT 1) <= ?',
                                    [$expiresUntil]
                                );
                        }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View')
                        ->icon('heroicon-m-eye')
                        ->color('info'),

                    Tables\Actions\Action::make('toggle_status')
                        ->label(fn (Model $record): string =>
                            Auth::user()->hasRole('super_admin') ?
                            ($record->is_active ? 'Deactivate' : 'Activate') :
                            'Deactivate'
                        )
                        ->icon(fn (Model $record): string =>
                            Auth::user()->hasRole('super_admin') ?
                            ($record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle') :
                            'heroicon-o-x-circle'
                        )
                        ->color(fn (Model $record): string =>
                            $record->is_active ? 'danger' : 'success'
                        )
                        ->requiresConfirmation()
                        ->action(function (Model $record) {
                            app(StatusService::class)->toggle($record, Auth::user());
                        })
                        ->visible(fn (Model $record): bool =>
                            // Only show the action if is_active is true
                            $record->is_active || Auth::user()->hasRole('super_admin')
                        ),

                    Tables\Actions\Action::make('accept')
                        ->label('Accept')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Model $record) =>
                            $record->is_active && in_array($record->status, ['pending', 'declined'])
                        )
                        ->action(function (Model $record) {
                            app(MemberStatusService::class)->accept($record);
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray'),
            ])
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
                            Notification::make()
                                ->success()
                                ->title('Members Activated')
                                ->body('Selected members have been activated successfully.')
                        )
                        ->visible(fn () => Auth::user() && Auth::user()->hasRole('super_admin')), // Only visible to super_admin

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Selected Members')
                        ->modalDescription('Are you sure you want to deactivate all selected members?')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('Members Deactivated')
                                ->body('Selected members have been deactivated successfully.')
                        ),
                    // Bulk Accept
                    Tables\Actions\BulkAction::make('bulk_accept')
                        ->label('Accept Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Accept Members')
                        ->modalDescription('Are you sure you want to accept all valid selected members?')
                        ->modalButton('Accept')
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                if ($record->is_active && in_array($record->status, ['pending', 'declined'])) {
                                    $record->update(['status' => 'accepted']);
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('Members Accepted')
                                ->body('Selected members were accepted successfully.')
                        ),

                    // Bulk Decline
                    Tables\Actions\BulkAction::make('bulk_decline')
                        ->label('Decline Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Decline Members')
                        ->modalDescription('Are you sure you want to decline all valid selected members?')
                        ->modalButton('Decline')
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                if ($record->is_active) {
                                    $record->update(['status' => 'declined']);
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('Members Declined')
                                ->body('Selected members were declined successfully.')
                        ),

                    Tables\Actions\BulkAction::make('export')
                        ->label('Export Selected')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function (Collection $records) {
                            // Export logic here - you can use Excel export or CSV
                            return response()->streamDownload(function () use ($records) {
                                $csv = Writer::createFromString('');
                                $csv->insertOne([
                                    'ID', 'CID', 'Name', 'Branch', 'Email',
                                    'Phone', 'Address', 'Age', 'Gender', 'Marital Status',
                                    'Occupation', 'Employment Status', 'Status', 'Joined Date', 'Account Name',
                                    'Account Number', 'Amount', 'Payment Date', 'Subscription Date', 'Remarks',
                                    'Note: Date Format'
                                ]);

                                // Sort records by last_name before inserting them
                                $records = $records->sortBy('last_name');

                                foreach ($records as $record) {

                                    $csv->insertOne([
                                        $record->id,
                                        $record->cid,
                                        $record->full_name,
                                        $record->branch->branch_name ?? 'N/A',
                                        $record->email,
                                        $record->contact_number,
                                        $record->full_address,
                                        $record->age,
                                        $record->gender_label,
                                        $record->marital_status_label,
                                        $record->occupation,
                                        $record->employment_status,
                                        $record->is_active ? 'Active' : 'Archived',
                                        $record->created_at->format('m/d/Y'),
                                        $record->latestSubscription->productAccount->product_name,
                                        $record->latestSubscription->productAccount->account_number,
                                        $record->latestSubscription->amount, '',
                                        $record->latestSubscription->expires_at->format('m/d/Y'),
                                        'RENEWAL',
                                        'month/day/Year (12/18/2025)',
                                    ]);
                                }

                                echo $csv->toString();
                            }, 'members-export-' . now()->format('Y-m-d-H-i-s') . '.csv');
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

                            $primary = $records->first();
                            $duplicates = $records->where('id', '!=', $primary->id);

                            $primary->loadMissing('subscriptions', 'productAccounts');

                            foreach ($duplicates as $duplicate) {
                                $duplicate->loadMissing('subscriptions', 'productAccounts');

                                foreach ($duplicate->subscriptions as $subscription) {
                                    $subscription->member_id = $primary->id;
                                    $subscription->save();
                                }

                                foreach ($duplicate->productAccounts as $account) {
                                    $account->member_id = $primary->id;
                                    $account->save();
                                }

                                $duplicate->is_active = false;
                                $duplicate->remark = 'Merged into: ' . $primary->full_name;
                                $duplicate->save();
                            }

                            $primary->save();

                            Notification::make()
                                ->title('Members merged successfully.')
                                ->success()
                                ->send();
                        }),


                ]),
            ])
            ->emptyStateHeading('No members found')
            ->emptyStateDescription('Get started by adding your first member.')
            ->emptyStateIcon('heroicon-o-users')
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->persistSortInSession()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->filtersFormColumns(2)
            ->paginated([10,25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->searchOnBlur()
            ->searchDebounce('750ms')
            // Disable polling for large datasets
            ->poll(null);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
            'view' => Pages\ViewMember::route('/{record}/view'),
        ];
    }
}
