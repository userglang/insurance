<?php

namespace App\Filament\Widgets;

use App\Models\Branch;
use App\Services\BranchSubscriptionService;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BranchSubscriptionTable extends BaseWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Branch Subscription Overview';
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Auth::user();

        return Auth::check() && $user->hasRole('super_admin');
    }

    protected function getTableQuery(): Builder|null
    {
        if (!Auth::check() || !Auth::user()->hasRole('super_admin')) {
            return Branch::query()->whereRaw('1 = 0');
        }

        return Branch::query()
            ->with(['members.subscriptions' => function ($query) {
                $query->where('expires_at', '>', now());
            }])
            ->whereHas('members.subscriptions', function ($query) {
                $query->where('expires_at', '>', now());
            });
    }

    protected function getTableColumns(): array
    {
        $service = app(BranchSubscriptionService::class);

        return [
            TextColumn::make('branch_name')
                ->label('Branch Name')
                ->sortable()
                ->searchable(),

            TextColumn::make('active_subscriptions')
                ->label('Active Subscriptions')
                ->state(fn ($record) => $service->countActiveSubscriptions($record)),

            TextColumn::make('total_members')
                ->label('Total Members')
                ->state(fn ($record) => $service->countTotalMembers($record)),

            TextColumn::make('subscription_rate')
                ->label('Subscription Rate')
                ->state(fn ($record) => $service->calculateSubscriptionRate($record)),

            TextColumn::make('expiring_soon')
                ->label('Expiring Soon')
                ->state(fn ($record) => $service->countExpiringSoonSubscriptions($record)),
        ];
    }

    protected function getTableFilters(): array
    {
        $service = app(BranchSubscriptionService::class);

        return [
            Tables\Filters\Filter::make('low_subscription_rate')
                ->label('Low Subscription Rate (< 60%)')
                ->query(function (Builder $query) use ($service) {
                    $branchIds = $service->getBranchIdsWithLowSubscriptionRate();
                    $query->whereIn('id', $branchIds);
                }),

            Tables\Filters\Filter::make('has_expiring_soon')
                ->label('Has Expiring Soon (Next 30 Days)')
                ->query(function (Builder $query) use ($service) {
                    $branchIds = $service->getBranchIdsWithExpiringSoon();
                    $query->whereIn('id', $branchIds);
                }),
        ];
    }
}
