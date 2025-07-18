<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Insurance;
use App\Models\Subscription;
use App\Models\Member;
use App\Models\ProductAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Container\Attributes\Auth;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Subscription Details')
                    ->description('Basic subscription information')
                    ->schema([
                        Forms\Components\Select::make('member_id')
                            ->label('Member Name')
                            ->relationship('member', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ', ' . $record->middle_name. ' ' . $record->last_name. ' ' . $record->suffix)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live() // 👈 This makes the field reactive
                            ->createOptionForm([
                                ...MemberResource::getPersonalInformation(),
                                ...MemberResource::getContactInformation(),
                                ...MemberResource::getGovernmentIDs(),
                            ])
                            ->columnSpan(2),

                        Forms\Components\Select::make('insurance_id')
                            ->label('Insurance Name')
                            ->relationship('insurance', 'insurance_name')
                            ->preload()
                            ->required()
                            ->placeholder('Select an account...')
                            ->default(fn () => Insurance::where('is_active', true)->value('id'))
                            ->columnSpan(1)
                            ->visible(true), // hides the field

                        Forms\Components\DatePicker::make('activated_at')
                            ->label('Subscription Date')
                            ->required()
                            ->default(now())
                            ->displayFormat('M j, Y')
                            ->columnSpan(1),


                        Forms\Components\TextInput::make('amount')
                            ->label('Subscription Amount')
                            ->required()
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(1)
                            ->step(0.01)
                            ->default(160.00)
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->required()
                            ->default(now())
                            ->displayFormat('M j, Y')
                            ->columnSpan(1),

                        Forms\Components\Select::make('product_account_id')
                            ->label('Account')
                            ->options(function (callable $get) {
                                $memberId = $get('member_id');

                                // Always include "CASH"
                                $options = ['0' => 'CASH'];

                                if ($memberId) {
                                    $accounts = ProductAccount::where('member_id', $memberId)
                                        ->pluck('product_name', 'id')
                                        ->toArray();

                                    $options += $accounts;
                                }

                                return $options;
                            })
                            ->preload()
                            ->required()
                            ->placeholder('Select an account...')
                            ->columnSpan(2),
                    ])->columns(2),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('remark')
                            ->label('Notes/Remarks')
                            ->placeholder('Add any additional notes about this subscription...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->collapsible(),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Member Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('insurance.insurance_name')
                    ->label('Insurance')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('productAccount.product_name')
                    ->label('Account')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('activated_at')
                    ->label('Activated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('success'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->color(fn (Subscription $record): string => match (true) {
                        $record->isExpired() => 'danger',
                        $record->daysRemaining() <= 30 => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('days_remaining')
                    ->label('Days Left')
                    ->state(fn (Subscription $record): string => $record->isExpired() ? 'Expired' : $record->daysRemaining() . ' days')
                    ->color(fn (Subscription $record): string => match (true) {
                        $record->isExpired() => 'danger',
                        $record->daysRemaining() <= 7 => 'danger',
                        $record->daysRemaining() <= 30 => 'warning',
                        default => 'success',
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('expires_at', $direction);
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'future' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'future' => 'Future',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'active' => $query->active(),
                            'expired' => $query->expired(),
                            'future' => $query->future(),
                            default => $query,
                        };
                    }),

                Filter::make('expires_soon')
                    ->label('Expires Soon (30 days)')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<=', now()->addDays(30))->where('expires_at', '>', now())),


                SelectFilter::make('member')
                    ->relationship('member', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ', ' . $record->middle_name. ' ' . $record->last_name. ' ' . $record->suffix)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('insurance')
                    ->label('Insurance Name')
                    ->relationship('insurance', 'insurance_name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View Subscription')
                        ->icon('heroicon-m-eye')
                        ->color('info'),

                    Tables\Actions\EditAction::make()
                        ->label('Edit Subscription')
                        ->icon('heroicon-m-pencil-square')
                        ->color('warning'),
                ])
                ->label('Actions')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size('sm')
                ->tooltip('More actions')
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s') // Auto-refresh every 30 seconds
            ->striped()
            ->persistFiltersInSession()
            ->persistSortInSession();
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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            // 'view' => Pages\ViewSubscription::route('/{record}'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            // You can add widgets here for dashboard overview
        ];
    }
}
