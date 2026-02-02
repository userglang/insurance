<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get current user's branch number, or null if super admin
     */
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

    /**
     * Generate cache key based on stat name and branch
     */
    private function buildCacheKey(string $prefix): string
    {
        $branch = $this->getBranchNumber();
        return "stats.dashboard.{$prefix}." . ($branch ?? 'all');
    }

    /**
     * Base member query scoped to branch
     */
    private function baseMemberQuery()
    {
        $query = Member::query();

        if ($branchNumber = $this->getBranchNumber()) {
            $query->where('branch_number', $branchNumber);
        }

        return $query;
    }

    /**
     * Base subscription query scoped to branch
     */
    private function baseSubscriptionQuery()
    {
        $query = Subscription::query();

        if ($branchNumber = $this->getBranchNumber()) {
            $query->whereHas('member', fn($q) => $q->where('branch_number', $branchNumber));
        }

        return $query;
    }

    /**
     * Get all dashboard stats (members + subscriptions) with caching
     */
    public function getDashboardStats(): array
    {
        return Cache::remember($this->buildCacheKey('stats'), now()->addMinutes(60), function () {
            $now = Carbon::now();
            $soon = $now->copy()->addDays(30);
            $branchNumber = $this->getBranchNumber();

            // === Member Stats ===
            $memberStats = $this->baseMemberQuery()
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'accepted' AND is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'declined' AND is_active = 1 THEN 1 ELSE 0 END) as declined,
                    SUM(CASE WHEN status = 'pending' AND is_active = 1 THEN 1 ELSE 0 END) as pending
                ")
                ->first();

            // === Subscription Stats ===

            // Subquery: latest subscription per member
            $latestSub = DB::table('subscriptions as s2')
                ->select('s2.member_id', DB::raw('MAX(s2.expires_at) as latest_expires_at'))
                ->groupBy('s2.member_id');

            $subscriptionQuery = DB::table('members as m')
                ->joinSub($latestSub, 'latest_sub', fn ($join) =>
                    $join->on('m.id', '=', 'latest_sub.member_id')
                )
                ->where('m.is_active', true)
                ->where('m.status', 'accepted');

            if ($branchNumber !== null) {
                $subscriptionQuery->where('m.branch_number', $branchNumber);
            }

            $subscriptionCounts = (clone $subscriptionQuery)
                ->selectRaw("
                    SUM(CASE WHEN latest_sub.latest_expires_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as expiring_soon,
                    SUM(CASE WHEN latest_sub.latest_expires_at < ? THEN 1 ELSE 0 END) as expired,
                    SUM(CASE WHEN latest_sub.latest_expires_at > ? THEN 1 ELSE 0 END) as active_subscriptions
                ", [$now, $soon, $now, $now])
                ->first();

            // Revenue This Month
            $revenue = $this->baseSubscriptionQuery()
                ->whereBetween('payment_date', [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth(),
                ])
                ->sum('amount');

            // === Final Aggregated Dashboard Data ===
            return [
                // Member Stats
                'totalMembers' => (int) $memberStats->total,
                'activeMembers' => (int) $memberStats->active,
                'declinedMembers' => (int) $memberStats->declined,
                'pendingMembers' => (int) $memberStats->pending,

                // Subscription Stats
                'expiringSoon' => (int) $subscriptionCounts->expiring_soon,
                'expired' => (int) $subscriptionCounts->expired,
                'activeSubscriptions' => (int) $subscriptionCounts->active_subscriptions,
                'revenueThisMonth' => (float) $revenue,
            ];
        });
    }

    // === Deprecated Individual Methods (Optional) ===

    // Members
    public function totalMembers(): int
    {
        return $this->getDashboardStats()['totalMembers'];
    }

    public function activeMembers(): int
    {
        return $this->getDashboardStats()['activeMembers'];
    }

    public function declinedMembers(): int
    {
        return $this->getDashboardStats()['declinedMembers'];
    }

    public function pendingMembers(): int
    {
        return $this->getDashboardStats()['pendingMembers'];
    }

    // Subscriptions
    public function expiringSoon(): int
    {
        return $this->getDashboardStats()['expiringSoon'];
    }

    public function expired(): int
    {
        return $this->getDashboardStats()['expired'];
    }

    public function activeSubscriptions(): int
    {
        return $this->getDashboardStats()['activeSubscriptions'];
    }

    public function revenueThisMonth(): float
    {
        return $this->getDashboardStats()['revenueThisMonth'];
    }
}
