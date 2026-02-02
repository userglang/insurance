<?php

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    private function getBranchNumber(): ?int
    {
        $user = Auth::user();

        if ($user && $user->hasRole('super_admin')) {
            return null;
        }

        return is_numeric($user?->branch?->branch_number)
            ? (int) $user->branch->branch_number
            : null;
    }

    private function buildCacheKey(string $prefix): string
    {
        $branch = $this->getBranchNumber();
        return "stats.subscriptions.{$prefix}." . ($branch ?? 'all');
    }

    /**
     * Base subscription query scoped to branch (used in revenue)
     */
    private function baseQuery()
    {
        $query = Subscription::query();

        if ($branchNumber = $this->getBranchNumber()) {
            $query->whereHas('member', fn ($q) => $q->where('branch_number', $branchNumber));
        }

        return $query;
    }

    /**
     * Get subscription stats in one query with caching:
     * expiringSoon, expired, activeSubscriptions, revenueThisMonth
     */
    public function getSubscriptionStats(): array
    {
        return Cache::remember($this->buildCacheKey('stats'), now()->addMinutes(60), function () {
            $now = Carbon::now();
            $soon = $now->copy()->addDays(30);
            $branchNumber = $this->getBranchNumber();

            // Subquery to get latest subscription expires_at per member
            $latestSub = DB::table('subscriptions as s2')
                ->select('s2.member_id', DB::raw('MAX(s2.expires_at) as latest_expires_at'))
                ->groupBy('s2.member_id');

            // Base query joining members with their latest subscription
            $query = DB::table('members as m')
                ->joinSub($latestSub, 'latest_sub', fn ($join) =>
                    $join->on('m.id', '=', 'latest_sub.member_id')
                )
                ->where('m.is_active', true)
                ->where('m.status', 'accepted');

            if ($branchNumber !== null) {
                $query->where('m.branch_number', $branchNumber);
            }

            // We fetch counts using conditional aggregation in raw SQL
            $counts = (clone $query)
                ->selectRaw("
                    SUM(CASE WHEN latest_sub.latest_expires_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as expiring_soon,
                    SUM(CASE WHEN latest_sub.latest_expires_at < ? THEN 1 ELSE 0 END) as expired,
                    SUM(CASE WHEN latest_sub.latest_expires_at > ? THEN 1 ELSE 0 END) as active_subscriptions
                ", [$now, $soon, $now, $now])
                ->first();

            // Revenue is a separate aggregate query on subscriptions with branch filter and date range
            $revenue = $this->baseQuery()
                ->whereBetween('payment_date', [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth(),
                ])
                ->sum('amount');

            return [
                'expiringSoon' => (int) $counts->expiring_soon,
                'expired' => (int) $counts->expired,
                'activeSubscriptions' => (int) $counts->active_subscriptions,
                'revenueThisMonth' => (float) $revenue,
            ];
        });
    }

    // Deprecated individual methods for backward compatibility
    public function expiringSoon(): int
    {
        return $this->getSubscriptionStats()['expiringSoon'];
    }

    public function expired(): int
    {
        return $this->getSubscriptionStats()['expired'];
    }

    public function activeSubscriptions(): int
    {
        return $this->getSubscriptionStats()['activeSubscriptions'];
    }

    public function revenueThisMonth(): float
    {
        return $this->getSubscriptionStats()['revenueThisMonth'];
    }
}
