<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Filament\Resources\MemberResource\RelationManagers;
use App\Models\Branch;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use League\Csv\Writer;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make()
                ->columnSpanFull()
                ->schema([
                    Wizard\Step::make('Personal Information')
                        ->schema(self::getPersonalInformation(),),
                    Wizard\Step::make('Contact Information')
                        ->schema(self::getContactInformation()),
                    Wizard\Step::make('Employment Information')
                        ->schema(self::getEmploymentInformation()),
                    Wizard\Step::make('Others')
                        ->schema(
                            array_merge(
                                self::getGovernmentIDs(),
                                self::getAdditionalInformation()
                            )
                        ),
                ]),

            ])
            ->columns(1)
            ->statePath('data');
    }

    public static function getPersonalInformation(): array
    {
        return
        [
            Forms\Components\Section::make('Personal Information')
                ->description('Basic personal details')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('cid')
                                ->label('CID')
                                ->maxLength(255)
                                ->helperText('Leave empty for auto-generation')
                                ->default(null),

                            Forms\Components\Select::make('branch_number')
                                ->label('Branch')
                                ->placeholder('Select a branch')
                                ->options(
                                    Branch::query()->pluck('branch_name', 'branch_number')
                                )
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
                                ->maxLength(255)
                                ->autocomplete('given-name'),

                            Forms\Components\TextInput::make('middle_name')
                                ->label('Middle Name')
                                ->placeholder('Enter middle name (optional)')
                                ->maxLength(255)
                                ->autocomplete('additional-name'),

                            Forms\Components\TextInput::make('last_name')
                                ->label('Last Name')
                                ->placeholder('Enter last name')
                                ->required()
                                ->maxLength(255)
                                ->autocomplete('family-name'),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('suffix')
                                ->label('Suffix')
                                ->placeholder('Jr., Sr., III, etc.')
                                ->maxLength(255)
                                ->autocomplete('honorific-suffix'),

                            Forms\Components\DatePicker::make('birth_date')
                                ->label('Date of Birth')
                                ->placeholder('Select birth date')
                                ->required()
                                ->displayFormat('F j, Y')
                                ->format('Y-m-d')
                                ->maxDate(now()->subYears(18))
                                ->helperText('Must be at least 18 years old'),
                        ]),

                    Forms\Components\Textarea::make('birth_place')
                        ->label('Place of Birth')
                        ->placeholder('Enter place of birth')
                        ->rows(2)
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
                                ])
                                ->required(),
                        ]),
                ]),
        ];
    }

    public static function getContactInformation(): array
    {
        return
        [
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
                                ->maxLength(255)
                                ->suffixIcon('heroicon-m-phone')
                                ->helperText('Include country code if international'),
                        ]),

                    Forms\Components\Fieldset::make('Address')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('house_number')
                                        ->label('House/Unit Number')
                                        ->placeholder('123, Unit 4B, etc.')
                                        ->maxLength(255)
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
                                        ->autocomplete('postal-code'),
                                ]),
                        ]),
                ]),
        ];
    }

    public static function getGovernmentIDs(): array
    {
        return
        [
            Forms\Components\Section::make('Government IDs')
                ->description('Social Security and Tax identification numbers')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('sss_gsis')
                                ->label('SSS/GSIS Number')
                                ->placeholder('12-3456789-0')
                                ->maxLength(255)
                                ->mask('99-9999999-9')
                                ->helperText('Format: XX-XXXXXXX-X'),

                            Forms\Components\TextInput::make('tin')
                                ->label('TIN Number')
                                ->placeholder('123-456-789-000')
                                ->maxLength(255)
                                ->mask('999-999-999-999')
                                ->helperText('Format: XXX-XXX-XXX-XXX'),
                        ]),
                ]),
        ];
    }

    public static function getEmploymentInformation(): array
    {
        return
        [
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
                                ->maxLength(255)
                                ->suffixIcon('heroicon-m-building-office'),
                        ]),

                    Forms\Components\Textarea::make('office_address')
                        ->label('Office Address')
                        ->placeholder('Enter complete office address')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getAdditionalInformation(): array
    {
        return
        [
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
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Avatar and Basic Info
                Tables\Columns\TextColumn::make('cid')
                    ->label('CID')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Member')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable(['first_name', 'last_name']),

                // Personal Details
                Tables\Columns\TextColumn::make('age')
                    ->label('Age')
                    ->suffix(' years old')
                    ->color('gray'),

                // Branch Information
                Tables\Columns\TextColumn::make('branch.branch_name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-building-office-2'),

                Tables\Columns\TextColumn::make('gender_label')
                    ->label('Gender')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn (string $state): string => match ($state) {
                        'Male' => 'blue',
                        'Female' => 'pink',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('marital_status_label')
                    ->label('Marital Status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn (string $state): string => match ($state) {
                        'Single' => 'green',
                        'Married' => 'yellow',
                        'Divorced' => 'red',
                        'Widowed' => 'purple',
                        default => 'gray',
                    }),

                // Contact Information
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied')
                    ->icon('heroicon-m-envelope')
                    ->color('blue')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(25),

                Tables\Columns\TextColumn::make('contact_number')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Phone copied')
                    ->icon('heroicon-m-phone')
                    ->color('green')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (string $state): string =>
                        $state ? preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $state) : 'N/A'
                    ),

                // Address Summary
                Tables\Columns\TextColumn::make('full_address')
                    ->label('Address')
                    ->searchable(['city', 'province', 'barangay'])
                    ->limit(30)
                    ->tooltip(fn (Model $record): string => $record->full_address)
                    ->icon('heroicon-m-map-pin')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),

                // Employment Information
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
                    ->label('Date Created')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
            ])
            ->filters([
                // Status Filter
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ])
                    ->placeholder('All Statuses'),

                // Branch Filter
                Tables\Filters\SelectFilter::make('branch_number')
                    ->label('Branch')
                    ->options(
                        Branch::query()->pluck('branch_name', 'branch_number')
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('All Branches'),

                // Gender Filter
                Tables\Filters\SelectFilter::make('gender')
                    ->label('Gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                        'other' => 'Other',
                    ])
                    ->placeholder('All Genders'),

                // Employment Status Filter
                Tables\Filters\SelectFilter::make('employment_status')
                    ->label('Employment Status')
                    ->options([
                        'employed' => 'Employed',
                        'self_employed' => 'Self-Employed',
                        'unemployed' => 'Unemployed',
                        'student' => 'Student',
                        'retired' => 'Retired',
                    ])
                    ->placeholder('All Employment Status'),

                // Age Range Filter
                Tables\Filters\Filter::make('age_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('age_from')
                                    ->label('Age From')
                                    ->numeric()
                                    ->placeholder('18'),
                                Forms\Components\TextInput::make('age_to')
                                    ->label('Age To')
                                    ->numeric()
                                    ->placeholder('65'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['age_from'],
                                fn (Builder $query, $age): Builder => $query->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= ?', [$age]),
                            )
                            ->when(
                                $data['age_to'],
                                fn (Builder $query, $age): Builder => $query->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) <= ?', [$age]),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['age_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Age from ' . $data['age_from'])
                                ->removeField('age_from');
                        }

                        if ($data['age_to'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Age to ' . $data['age_to'])
                                ->removeField('age_to');
                        }

                        return $indicators;
                    }),

                // Date Range Filter
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Joined From')
                            ->placeholder('Select start date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Joined Until')
                            ->placeholder('Select end date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['created_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Joined from ' . Carbon::parse($data['created_from'])->toFormattedDateString())
                                ->removeField('created_from');
                        }

                        if ($data['created_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Joined until ' . Carbon::parse($data['created_until'])->toFormattedDateString())
                                ->removeField('created_until');
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View')
                        ->icon('heroicon-m-eye')
                        ->color('info'),

                    Tables\Actions\EditAction::make()
                        ->label('Edit')
                        ->icon('heroicon-m-pencil-square')
                        ->color('warning'),

                    Tables\Actions\Action::make('toggle_status')
                        ->label(fn (Model $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                        ->icon(fn (Model $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn (Model $record): string => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Model $record): string => $record->is_active ? 'Deactivate Member' : 'Activate Member')
                        ->modalDescription(fn (Model $record): string => $record->is_active
                            ? 'Are you sure you want to deactivate this member? They will no longer have access to services.'
                            : 'Are you sure you want to activate this member? They will regain access to services.'
                        )
                        ->action(fn (Model $record) => $record->update(['is_active' => !$record->is_active]))
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('Status Updated')
                                ->body('Member status has been updated successfully.')
                        ),
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

                    Tables\Actions\BulkAction::make('export')
                        ->label('Export Selected')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function (Collection $records) {
                            // Export logic here - you can use Excel export or CSV
                            return response()->streamDownload(function () use ($records) {
                                $csv = Writer::createFromString('');
                                $csv->insertOne([
                                    'ID', 'Name', 'Branch', 'Email', 'Phone', 'Address',
                                    'Age', 'Gender', 'Marital Status', 'Occupation',
                                    'Employment Status', 'Status', 'Joined Date'
                                ]);

                                foreach ($records as $record) {
                                    $csv->insertOne([
                                        $record->cid,
                                        $record->full_name,
                                        $record->branch->name ?? 'N/A',
                                        $record->email,
                                        $record->contact_number,
                                        $record->full_address,
                                        $record->age,
                                        $record->gender_label,
                                        $record->marital_status_label,
                                        $record->occupation,
                                        $record->employment_status,
                                        $record->is_active ? 'Active' : 'Inactive',
                                        $record->created_at->format('Y-m-d'),
                                    ]);
                                }

                                echo $csv->toString();
                            }, 'members-export-' . now()->format('Y-m-d-H-i-s') . '.csv');
                        }),
                ]),
            ])
            ->emptyStateHeading('No members found')
            ->emptyStateDescription('Get started by adding your first member to the system.')
            ->emptyStateIcon('heroicon-o-users')
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->persistSortInSession()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->filtersFormColumns(3)
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->poll('60s') // Auto-refresh every 60 seconds
            ->deferLoading()
            ->searchOnBlur()
            ->searchDebounce('500ms');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
