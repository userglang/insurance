<?php

namespace App\Filament\Widgets;

use App\Models\Branch;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;

class BranchSubscriptionTable extends BaseWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Branch Subscription Overview';

    protected int | string | array $columnSpan = 'full';

    /**
     * Method 1: Using canView() method (recommended)
     */
    public static function canView(): bool
    {
        $user = Auth::user();
        return Auth::check() && $user->hasRole('super_admin');
    }

    /**
     * Method 2: Alternative using shouldRegisterNavigation()
     * Uncomment this method if you prefer this approach instead of canView()
     */
    // public static function shouldRegisterNavigation(): bool
    // {
    //     $user = Auth::user();
    //     return Auth::check() && $user->hasRole('super_admin');
    // }

    /**
     * Method 3: Using getWidgetVisibility() for more complex logic
     * Uncomment this method if you need more complex visibility logic
     */
    // protected function getWidgetVisibility(): bool
    // {
    //     $user = Auth::user();
    //
    //     // Example: Show for super_admin or admin with special permission
    //     return Auth::check() && (
    //         $user->hasRole('super_admin') ||
    //         ($user->hasRole('admin') && $user->hasPermission('view_branch_subscriptions'))
    //     );
    // }

    /**
     * Required: Must match parent method signature exactly
     */
    protected function getTableQuery(): Builder | Relation | null
    {
        // Double-check authorization at query level as extra security
        $user = Auth::user();
        if (!Auth::check() || !$user->hasRole('super_admin')) {
            return Branch::query()->whereRaw('1 = 0'); // Return empty query
        }

        return Branch::query()
            ->with(['members.subscriptions' => function ($query) {
                $query->where('expires_at', '>', now());
            }]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('branch_name')
                ->label('Branch Name')
                ->sortable()
                ->searchable(),

            TextColumn::make('active_subscriptions')
                ->label('Active Subscriptions')
                ->state(function ($record) {
                    return $record->members
                        ->flatMap(fn ($member) =>
                        $member->subscriptions->map(fn ($sub) => $sub->member_id . '-' . $sub->insurance_id)
                        )
                        ->unique()
                        ->count();
                }),

            TextColumn::make('total_members')
                ->label('Total Members')
                ->state(fn ($record) => $record->members->count()),

            TextColumn::make('subscription_rate')
                ->label('Subscription Rate')
                ->state(function ($record) {
                    $totalMembers = $record->members->count();
                    $activeSubs = $record->members
                        ->flatMap(fn ($member) =>
                        $member->subscriptions->map(fn ($sub) => $sub->member_id . '-' . $sub->insurance_id)
                        )
                        ->unique()
                        ->count();

                    return $totalMembers > 0
                        ? number_format(($activeSubs / $totalMembers) * 100, 1) . '%'
                        : '0%';
                }),

            TextColumn::make('expiring_soon')
                ->label('Expiring Soon')
                ->state(function ($record) {
                    return $record->members
                        ->flatMap(function ($member) {
                            return $member->subscriptions
                                ->filter(function ($subscription) {
                                    return $subscription->expires_at >= now()
                                        && $subscription->expires_at <= now()->addDays(30);
                                });
                        })
                        ->count();
                }),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Tables\Filters\Filter::make('low_subscription_rate')
                ->label('Low Subscription Rate (< 60%)')
                ->query(function (Builder $query) {
                    $branchIds = Branch::query()
                        ->with(['members.subscriptions' => fn ($q) => $q->where('expires_at', '>', now())])
                        ->get()
                        ->filter(function ($branch) {
                            $totalMembers = $branch->members->count();
                            $activeSubs = $branch->members
                                ->flatMap(fn ($member) =>
                                $member->subscriptions->map(fn ($sub) => $sub->member_id . '-' . $sub->insurance_id)
                                )
                                ->unique()
                                ->count();

                            $rate = $totalMembers > 0
                                ? ($activeSubs / $totalMembers) * 100
                                : 0;

                            return $rate < 60;
                        })
                        ->pluck('id')
                        ->toArray();

                    $query->whereIn('id', $branchIds);
                }),

            Tables\Filters\Filter::make('has_expiring_soon')
                ->label('Has Expiring Soon (Next 30 Days)')
                ->query(function (Builder $query) {
                    $branchIds = Branch::query()
                        ->with(['members.subscriptions' => fn ($q) => $q->whereBetween('expires_at', [now(), now()->addDays(30)])])
                        ->get()
                        ->filter(function ($branch) {
                            $expiringSoon = $branch->members
                                ->flatMap(fn ($member) =>
                                $member->subscriptions->filter(fn ($sub) =>
                                    $sub->expires_at >= now() && $sub->expires_at <= now()->addDays(30)
                                )
                                )
                                ->count();

                            return $expiringSoon > 0;
                        })
                        ->pluck('id')
                        ->toArray();

                    $query->whereIn('id', $branchIds);
                }),
        ];
    }
}
