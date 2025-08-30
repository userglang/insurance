<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Branch;
use App\Models\Member;
use App\Jobs\ProcessBulkMemberOperation;
use App\Services\MemberExportService;
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

    public static function getNavigationBadge(): ?string
    {
        // Cache expensive badge calculations for 5 minutes
        return Cache::remember('member_navigation_badge', 300, function () {
            $counts = DB::table('members as m')
                ->leftJoin('subscriptions as s', function ($join) {
                    $join->on('m.id', '=', 's.member_id')
                        ->whereRaw('s.id = (SELECT id FROM subscriptions WHERE member_id = m.id ORDER BY expires_at DESC LIMIT 1)');
                })
                ->where('m.is_active', true)
                ->where('m.status', 'accepted')
                ->selectRaw('
                    COUNT(CASE WHEN s.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
                    COUNT(CASE WHEN s.expires_at < NOW() THEN 1 END) as expired
                ')
                ->first();

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
        return parent::getGlobalSearchEloquentQuery()
            ->select(['id', 'cid', 'first_name', 'last_name', 'branch_number', 'is_active'])
            // ->where('is_active', true)
            ->orderBy('is_active', 'desc')
            ->with('branch:id,branch_number,branch_name');
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->full_name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('Branch') => $record->branch?->branch_name ?? 'N/A',
            __('Status') => $record->is_active ? 'Active' : 'Inactive',
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
                                ->options(fn () => Cache::remember('branches_options', 3600,
                                    fn () => Branch::pluck('branch_name', 'branch_number')
                                ))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Select the branch this client belongs to'),
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
                                ->placeholder('+63 912 345 6789')
                                ->maxLength(20)
                                ->tel()
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
                                ->placeholder('+63 2 123 4567')
                                ->maxLength(20)
                                ->tel()
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

    public static function tableQuery(): Builder
    {
        // Optimized base query with selective fields and indexed columns
        return parent::tableQuery()
            ->select([
                'members.id',
                'members.cid',
                'members.first_name',
                'members.middle_name',
                'members.last_name',
                'members.email',
                'members.status',
                'members.birth_date',
                'members.gender',
                'members.marital_status',
                'members.occupation',
                'members.employment_status',
                'members.branch_number',
                'members.contact_number',
                'members.city',
                'members.province',
                'members.barangay',
                'members.created_at',
                'members.is_active',
            ])
            ->with([
                'branch:id,branch_number,branch_name'
            ]);
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Member Name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'accepted' => 'success',
                        'pending' => 'warning',
                        'declined' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('subscriptions.expires_at')
                    ->label('Expiration Date')
                    ->date('M j, Y')
                    ->getStateUsing(fn ($record) =>
                    $record->subscriptions->sortByDesc('expires_at')->first()?->expires_at
                    )
                    ->color(fn ($state) =>
                    $state instanceof \Illuminate\Support\Carbon && $state->lt(now())
                        ? 'danger'
                        : ($state && $state->lt(now()->addDays(30))
                        ? 'warning'
                        : 'success')
                    )
                    ->description(function ($record) {
                        $expiresAt = $record->subscriptions->sortByDesc('expires_at')->first()?->expires_at;

                        if (! $expiresAt instanceof \Illuminate\Support\Carbon) {
                            return 'No subscription';
                        }

                        return $expiresAt->lt(now())
                            ? 'Expired'
                            : ($expiresAt->lt(now()->addDays(30)) ? 'Expires soon' : 'Active');
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
                        0 => 'Inactive',
                    ])
                    ->placeholder('All Statuses'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Application Status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'declined' => 'Declined',
                    ])
                    ->placeholder('All Applications'),

                Tables\Filters\SelectFilter::make('branch_number')
                    ->label('Branch')
                    ->options(fn () => Cache::remember('branch_filter_options', 3600,
                        fn () => Branch::pluck('branch_name', 'branch_number')
                    ))
                    ->searchable()
                    ->preload()
                    ->placeholder('All Branches'),

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
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['subscription_filter'])) {
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

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Joined From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Joined Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when($data['created_until'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date)
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
                        ->label(fn (Model $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                        ->icon(fn (Model $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn (Model $record): string => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(function (Model $record) {
                            try {
                                $record->update(['is_active' => !$record->is_active]);
                                Notification::make()
                                    ->success()
                                    ->title('Status Updated')
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Error updating status')
                                    ->body('Please try again.')
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('accept')
                        ->label('Accept')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Model $record) =>
                            $record->is_active && in_array($record->status, ['pending', 'declined'])
                        )
                        ->action(function (Model $record) {
                            try {
                                $record->update(['status' => 'accepted']);
                                Notification::make()
                                    ->success()
                                    ->title('Member Accepted')
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Error accepting member')
                                    ->send();
                            }
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
                        ),

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
                                    'ID', 'CID', 'Name', 'Branch', 'Email', 'Phone', 'Address',
                                    'Age', 'Gender', 'Marital Status', 'Occupation',
                                    'Employment Status', 'Status', 'Joined Date', 'Account Name', 'Account Number', 'Amount', 'Payment Date', 'Subscription Date', 'Remarks'
                                ]);

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
                                        $record->created_at->format('Y-m-d'),
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
            ->paginated([10,25, 50, 100])
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
