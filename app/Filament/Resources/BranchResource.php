<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Filament\Resources\BranchResource\RelationManagers;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Branches';

    protected static ?string $modelLabel = 'Branch';

    protected static ?string $pluralModelLabel = 'Branches';

    protected static ?string $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::count() > 10 ? 'warning' : 'primary';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Branch Information')
                    ->description('Enter the basic details for this branch')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        ...self::getBranchInformation(),
                        ...self::getStatusAndSettings(),
                    ]),
            ]);
    }

    public static function getBranchInformation(): array
    {
        return
        [
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('branch_number')
                        ->label('Branch Number')
                        ->placeholder('Enter branch number (e.g., B001)')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Unique identifier for the branch')
                        ->validationAttribute('branch number')
                        ->unique(ignoreRecord: true)
                        ->alphaDash(),

                    Forms\Components\TextInput::make('code')
                        ->label('Branch Code')
                        ->placeholder('Enter branch code (e.g., DT-001)')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Short code for quick identification')
                        ->validationAttribute('branch code')
                        ->unique(ignoreRecord: true)
                        ->alphaDash(),
                ]),

            Forms\Components\TextInput::make('branch_name')
                ->label('Branch Name')
                ->placeholder('Enter branch name (e.g., Downtown Branch)')
                ->required()
                ->maxLength(255)
                ->helperText('Full name of the branch location')
                ->validationAttribute('branch name')
                ->columnSpanFull(),

            Forms\Components\Textarea::make('address')
                ->label('Address')
                ->placeholder('Enter complete address...')
                ->maxLength(500)
                ->rows(3)
                ->helperText('Complete address including street, city, and postal code')
                ->columnSpanFull(),
        ];
    }

    public static function getStatusAndSettings(): array
    {
        return
        [
            Forms\Components\Section::make('Status & Settings')
                ->description('Configure branch status and settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active Status')
                        ->helperText('Toggle to activate or deactivate this branch')
                        ->default(true)
                        ->onColor('success')
                        ->offColor('danger')
                        ->onIcon('heroicon-s-check-circle')
                        ->offIcon('heroicon-s-x-circle')
                        ->inline(false),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch_number')
                    ->label('Branch #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click to copy')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('branch_name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('medium')
                    ->description(fn ($record) => $record->code ?? 'No code'),

                Tables\Columns\TextColumn::make('address')
                    ->label('Location')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->address)
                    ->placeholder('No address provided')
                    ->icon('heroicon-o-map-pin')
                    ->color('gray'),

                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->colors([
                        'success' => 'Active',
                        'danger' => 'Inactive',
                    ])
                    ->icons([
                        'heroicon-s-check-circle' => 'Active',
                        'heroicon-s-x-circle' => 'Inactive',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray')
                    ->size('sm')
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ])
                    ->placeholder('All statuses'),

                Tables\Filters\Filter::make('recent')
                    ->label('Recently Added')
                    ->query(fn ($query) => $query->where('created_at', '>=', now()->subDays(7)))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->color('warning'),
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
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
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-s-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-s-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ])
                ->label('Bulk Actions'),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->extremePaginationLinks()
            ->emptyStateHeading('No branches found')
            ->emptyStateDescription('Get started by creating your first branch location.')
            ->emptyStateIcon('heroicon-o-building-office')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create First Branch')
                    ->icon('heroicon-s-plus'),
            ]);
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
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
