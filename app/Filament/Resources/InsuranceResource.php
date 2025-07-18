<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsuranceResource\Pages;
use App\Filament\Resources\InsuranceResource\RelationManagers;
use App\Models\Insurance;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class InsuranceResource extends Resource
{
    protected static ?string $model = Insurance::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                ...self::getInsuranceInformation(),
                ...self::getCoverageDetails(),
            ]);
    }

    public static function getInsuranceInformation(): array
    {
        return
        [
            Forms\Components\Section::make('Insurance Information')
                ->description('Enter the basic insurance details and coverage information')
                ->icon('heroicon-o-shield-check')
                ->collapsible()
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('insurance_name')
                                ->label('Insurance Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Enter insurance plan name')
                                ->helperText('The official name of the insurance plan or policy')
                                ->autocomplete('off')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (string $state, callable $set) {
                                    // Auto-capitalize first letter of each word
                                    $set('insurance_name', ucwords(strtolower($state)));
                                }),

                            Forms\Components\Select::make('insurance_type')
                                ->label('Insurance Type')
                                ->required()
                                ->placeholder('Select insurance type')
                                ->options([
                                    'Health' => 'Health Insurance',
                                    'Life' => 'Life Insurance',
                                    'Auto' => 'Auto Insurance',
                                    'Home' => 'Home Insurance',
                                    'Business' => 'Business Insurance',
                                    'Travel' => 'Travel Insurance',
                                    'Other' => 'Other',
                                ])
                                ->searchable()
                                ->helperText('Choose the category that best describes this insurance')
                                ->native(false)
                                ->suffixIcon('heroicon-o-chevron-down'),
                        ])
                ])
        ];


    }

    public static function getCoverageDetails(): array
    {
        return
        [
            Forms\Components\Section::make('Coverage Details')
                ->description('Provide additional information about the coverage and benefits')
                ->icon('heroicon-o-document-text')
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('amount')
                        ->label('Coverage Amount')
                        ->numeric()
                        ->prefix('$')
                        ->placeholder('0.00')
                        ->helperText('Enter the coverage amount or premium (leave blank if not applicable)')
                        ->minValue(0)
                        ->maxValue(999999999.99)
                        ->step(0.01)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $set('amount', number_format((float)$state, 2, '.', ''));
                            }
                        })
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('calculate')
                                ->icon('heroicon-o-calculator')
                                ->action(function (callable $get, callable $set) {
                                    // You can add calculation logic here
                                    $set('amount', '0.00');
                                })
                                ->tooltip('Calculate coverage amount')
                        ),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->placeholder('Enter detailed description of the insurance coverage, benefits, terms, and conditions...')
                        ->helperText('Provide comprehensive information about what this insurance covers')
                        ->rows(4)
                        ->maxLength(1000)
                        ->columnSpanFull()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $state, callable $set) {
                            // Auto-capitalize first letter
                            if ($state) {
                                $set('description', ucfirst($state));
                            }
                        })
                        ->hint(function ($state) {
                            return (1000 - strlen($state ?? '')) . ' characters remaining';
                        })
                        ->hintColor('gray'),
                ])
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('insurance_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'health',
                        'success' => 'life',
                        'warning' => 'auto',
                        'danger' => 'home',
                        'info' => 'business',
                        'secondary' => 'travel',
                        'slate' => 'other',
                    ])
                    ->icons([
                        'heroicon-o-heart' => 'health',
                        'heroicon-o-user' => 'life',
                        'heroicon-o-truck' => 'auto',
                        'heroicon-o-home' => 'home',
                        'heroicon-o-briefcase' => 'business',
                        'heroicon-o-globe-alt' => 'travel',
                        'heroicon-o-question-mark-circle' => 'other',
                    ])
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('insurance_name')
                    ->label('Insurance Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->copyable()
                    ->copyMessage('Insurance name copied!')
                    ->tooltip('Click to copy')
                    ->wrap(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->sortable()
                    ->placeholder('Not specified')
                    ->color('success')
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) <= 50 ? null : $state;
                    })
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('insurance_type')
                    ->label('Insurance Type')
                    ->options([
                        'health' => 'Health Insurance',
                        'life' => 'Life Insurance',
                        'auto' => 'Auto Insurance',
                        'home' => 'Home Insurance',
                        'business' => 'Business Insurance',
                        'travel' => 'Travel Insurance',
                        'disability' => 'Disability Insurance',
                        'other' => 'Other',
                    ])
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All insurance policies')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->queries(
                        true: fn(Builder $query) => $query->where('is_active', true),
                        false: fn(Builder $query) => $query->where('is_active', false),
                    ),

                Tables\Filters\Filter::make('amount_range')
                    ->label('Coverage Amount')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->label('From')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\TextInput::make('amount_to')
                            ->label('To')
                            ->numeric()
                            ->prefix('$'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn(Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn(Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['amount_from'] ?? null) {
                            $indicators[] = 'Amount from $' . number_format($data['amount_from'], 2);
                        }

                        if ($data['amount_to'] ?? null) {
                            $indicators[] = 'Amount to $' . number_format($data['amount_to'], 2);
                        }

                        return $indicators;
                    }),

                Tables\Filters\Filter::make('created_at')
                    ->label('Created Date')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View Details')
                        ->icon('heroicon-m-eye')
                        ->color('info')
                        ->modalHeading('Insurance Policy Details')
                        ->modalWidth('4xl'),

                    Tables\Actions\EditAction::make()
                        ->label('Edit Policy')
                        ->icon('heroicon-m-pencil-square')
                        ->color('warning')
                        ->modalHeading('Edit Insurance Policy')
                        ->modalWidth('4xl'),

                    Tables\Actions\Action::make('toggle_status')
                        ->label(fn($record) => $record->is_active ? 'Deactivate' : 'Activate')
                        ->icon(fn($record) => $record->is_active ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                        ->color(fn($record) => $record->is_active ? 'danger' : 'success')
                        ->action(function ($record) {
                            $record->update(['is_active' => !$record->is_active]);
                        })
                        ->requiresConfirmation()
                        ->modalHeading(fn($record) => $record->is_active ? 'Deactivate Policy' : 'Activate Policy')
                        ->modalDescription(fn($record) => $record->is_active
                            ? 'Are you sure you want to deactivate this insurance policy?'
                            : 'Are you sure you want to activate this insurance policy?')
                        ->successNotificationTitle(fn($record) => $record->is_active
                            ? 'Insurance policy activated successfully'
                            : 'Insurance policy deactivated successfully'),

                    Tables\Actions\DeleteAction::make()
                        ->label('Delete')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Insurance Policy')
                        ->modalDescription('Are you sure you want to delete this insurance policy? This action cannot be undone.')
                        ->successNotificationTitle('Insurance policy deleted successfully'),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size(ActionSize::Small)
                    ->color('gray')
                    ->tooltip('More actions'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each->update(['is_active' => true]);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Activate Insurance Policies')
                        ->modalDescription('Are you sure you want to activate the selected insurance policies?'),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            $records->each->update(['is_active' => false]);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Insurance Policies')
                        ->modalDescription('Are you sure you want to deactivate the selected insurance policies?'),
                ]),
            ])
            ->emptyStateHeading('No insurance policies found')
            ->emptyStateDescription('Create your first insurance policy to get started.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
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
            'index' => Pages\ListInsurances::route('/'),
            'create' => Pages\CreateInsurance::route('/create'),
            'edit' => Pages\EditInsurance::route('/{record}/edit'),
        ];
    }
}
