<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SubscriptionTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full'; // use 'full', '2', '3' etc., based on your layout

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Subscription::query()
                    ->whereIn('id', function ($query) {
                        $query->select(DB::raw('MAX(id)'))
                            ->from('subscriptions')
                            ->groupBy('member_id', 'insurance_id');
                    })
                    ->whereHas('member', function (Builder $query) {
                        $query->where('is_active', true);
                    })
                    ->with(['member', 'insurance'])
                    ->limit(10) // Show only latest 10 entries
            )
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Member')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('insurance.insurance_name')
                    ->label('Insurance')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('PHP')
                    ->label('Amount'),

                Tables\Columns\TextColumn::make('activated_at')
                    ->label('Activated')
                    ->date(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->color(fn (Subscription $record) => match (true) {
                        $record->isExpired() => 'danger',
                        $record->daysRemaining() <= 30 => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'future' => 'warning',
                        default => 'gray',
                    }),
            ]);
    }


}
