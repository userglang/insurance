<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use App\Filament\Resources\ProductAccountResource;
use App\Filament\Resources\SubscriptionResource;
use App\Models\ProductAccount;
use App\Models\Subscription;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Forms;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public function form(Form $form): Form
    {
        return $form->schema([
            ...SubscriptionResource::getSubscriptionDetails(),
            ...self::getProductAccountID(),
            ...SubscriptionResource::getAdditionalInformation(),
        ]);
    }

    public static function getProductAccountID(): array
    {
        return [
            Forms\Components\Select::make('product_account_id')
                ->label('Account')
                ->options(function ($livewire) {
                    $memberId = $livewire->getOwnerRecord()->id;

                    $accounts = ProductAccount::where('member_id', $memberId)
                        ->selectRaw('
                            id,
                            CASE
                                WHEN UPPER(product_name) = "CASH" THEN "CASH"
                                ELSE CONCAT(UPPER(product_name), " (", account_number, ")")
                            END as display_name
                        ')
                        ->get();

                    $options = $accounts->pluck('display_name', 'id')->toArray();

                    $hasCash = $accounts->contains(function ($account) {
                        return strtoupper($account->display_name) === 'CASH';
                    });

                    if (! $hasCash) {
                        $options = ['0' => 'CASH'] + $options;
                    }

                    return $options;
                })
                ->createOptionForm([
                    ...ProductAccountResource::getProductAccountDetails(),
                ])
                ->createOptionUsing(function (array $data, $livewire) {
                    $memberId = $livewire->getOwnerRecord()->id;

                    $productAccount = ProductAccount::create([
                        'member_id' => $memberId,
                        'product_name' => $data['product_name'],
                        'account_number' => $data['account_number'],
                    ]);

                    return $productAccount->id;
                })
                ->required()
                ->preload()
                ->searchable()
                ->placeholder('Select an account...')
                ->columnSpan(2),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('insurance.insurance_name')
                    ->label('Insurance Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('productAccount.product_name')
                    ->label('Account')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn ($record) =>
                        ($record->productAccount?->account_number && $record->productAccount->account_number != 0)
                            ? $record->productAccount->account_number
                            : null
                    )
                    ->wrap(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Payment Date')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(true)
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

                Tables\Columns\TextColumn::make('remark')
                    ->label('Remarks')
                    ->limit(30)
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn (Model $record): string => (string) $record->remark)
                    ->wrap(),

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

                SelectFilter::make('insurance')
                    ->label('Insurance Name')
                    ->relationship('insurance', 'insurance_name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire) {
                        if ($data['product_account_id'] === '0') {
                            $cashAccount = ProductAccount::create([
                                'member_id' => $livewire->getOwnerRecord()->id,
                                'product_name' => 'CASH',
                                'account_number' => '0',
                            ]);

                            $data['product_account_id'] = $cashAccount->id;
                        }

                        return $data;
                    })
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
            ->poll('30s')
            ->striped()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->emptyStateHeading('No subscriptions found');
    }
}
