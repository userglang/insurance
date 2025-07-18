<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Branch;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\{TextInput, Select, Toggle, Section, Placeholder};
use Filament\Tables\Columns\{TextColumn, IconColumn, BadgeColumn};
use Filament\Tables\Filters\{SelectFilter, TernaryFilter};
use Filament\Tables\Actions\{ViewAction, EditAction, DeleteAction, BulkAction};
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Team Members';
    protected static ?string $pluralModelLabel = 'Team Members';
    protected static ?string $modelLabel = 'Team Member';
    protected static ?string $slug = 'system-users';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationGroup = 'User Management';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['branch']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'branch.branch_name', 'is_active'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Branch' => $record->branch?->branch_name,
            'Status' => $record->is_active ? 'Active' : 'Inactive',
        ];
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            ...self::getPersonalInformation(),
            ...self::getSecuritySettings(),
            ...self::getWorkAssignment(),
        ]);
    }

    public static function getPersonalInformation(): array
    {
        return
        [
            Section::make('👤 Personal Information')
                ->description('Enter the basic details for this team member')
                ->schema([
                    TextInput::make('name')
                        ->label('Full Name')
                        ->placeholder('e.g., John Doe')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Enter the person\'s full name as it should appear in the system'),

                    TextInput::make('email')
                        ->label('Email Address')
                        ->placeholder('e.g., john@company.com')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('This will be used for login and notifications')
                        ->suffixIcon('heroicon-m-envelope'),
                ])
                ->columns(2),
        ];
    }

    public static function getSecuritySettings(): array
    {
        return
        [
            Section::make('🔐 Security Settings')
                ->description('Configure login credentials and permissions')
                ->schema([
                    TextInput::make('password')
                        ->label('Password')
                        ->placeholder('Enter a secure password')
                        ->password()
                        ->default('password123')
                        ->revealable()
                        ->required(fn ($livewire) => $livewire instanceof Pages\CreateUser)
                        ->dehydrateStateUsing(fn ($state) => !empty($state) ? Hash::make($state) : null)
                        ->maxLength(255)
                        ->minLength(8)
                        ->helperText('Password must be at least 8 characters long')
                        ->hiddenOn('edit')
                        ->suffixIcon('heroicon-m-key'),

                    Placeholder::make('password_change_note')
                        ->label('Password Management')
                        ->content('To change the password, the user should use the "Forgot Password" feature or contact an administrator.')
                        ->visibleOn('edit'),
                ])
                ->columns(1),
        ];
    }

    public static function getWorkAssignment(): array
    {
        return
        [
            Section::make('🏢 Work Assignment')
                ->description('Assign the team member to their work location')
                ->schema([
                    Select::make('branch_id')
                        ->label('Branch/Location')
                        ->placeholder('Select a branch...')
                        ->options(
                            Branch::query()->pluck('branch_name', 'id')
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Choose the primary branch where this person will work')
                        ->suffixIcon('heroicon-m-building-office'),

                    Toggle::make('is_active')
                        ->label('Account Status')
                        ->helperText('Turn off to temporarily disable login access')
                        ->default(true)
                        ->onColor('success')
                        ->offColor('danger')
                        ->onIcon('heroicon-m-check-circle')
                        ->offIcon('heroicon-m-x-circle'),
                ])
                ->columns(2),
        ];
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-m-user'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copied!')
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('branch.branch_name')
                    ->label('Branch')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-building-office'),

                BadgeColumn::make('is_active')
                    ->label('Status')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->colors([
                        'success' => 'Active',
                        'danger' => 'Inactive',
                    ])
                    ->icons([
                        'heroicon-m-check-circle' => 'Active',
                        'heroicon-m-x-circle' => 'Inactive',
                    ]),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable()
                    ->since()
                    ->icon('heroicon-m-calendar'),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->relationship('branch', 'branch_name')
                    ->label('Filter by Branch')
                    ->placeholder('All branches')
                    ->multiple(),

                TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->placeholder('All statuses')
                    ->trueLabel('Active users only')
                    ->falseLabel('Inactive users only')
                    ->default(null),
            ])
            ->actions([
                    Tables\Actions\ActionGroup::make([
                        EditAction::make()
                            ->label('Edit')
                            ->icon('heroicon-m-pencil-square')
                            ->color('warning'),

                        Action::make('resetPassword')
                            ->label('Reset Password')
                            ->icon('heroicon-m-key')
                            ->color('info')
                            ->requiresConfirmation()
                            ->modalHeading('Reset Password')
                            ->modalDescription('Are you sure you want to reset the password for this user? It will be set to the default: password123.')
                            ->modalSubmitActionLabel('Yes, reset')
                            // ->visible(fn ($record) => Auth::user()->can('update_user', $record))
                            ->action(function ($record) {
                                $record->update([
                                    'password' => Hash::make('password123'),
                                ]);
                            })
                            ->successNotification(
                                Notification::make()
                                    ->success()
                                    ->title('Password Reset')
                                    ->body('The password has been reset to the default: password123.')
                            ),

                        DeleteAction::make()
                            ->label('Delete')
                            ->icon('heroicon-m-trash')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Delete Team Member')
                            ->modalDescription('Are you sure you want to delete this team member? This action cannot be undone.')
                            ->modalSubmitActionLabel('Yes, delete')
                            ->successNotification(
                                Notification::make()
                                    ->success()
                                    ->title('Team member deleted')
                                    ->body('The team member has been successfully removed.')
                            ),
                    ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray'),

            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->label('Delete selected')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Selected Team Members')
                    ->modalDescription('Are you sure you want to delete these team members? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete all')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Team members deleted')
                            ->body('The selected team members have been successfully removed.')
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->recordUrl(null) // Disable row clicking to prevent accidental navigation
            ->emptyStateHeading('No team members found')
            ->emptyStateDescription('Get started by adding your first team member to the system.')
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }


}
