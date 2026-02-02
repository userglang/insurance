<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BranchSubscriptionService
{
    protected int $cacheTtl = 3600; // Cache for 1 hour (seconds)

    public function getBranchesWithActiveSubscriptions()
    {
        return Cache::remember('branches.active_subscriptions', $this->cacheTtl, function () {
            return Branch::query()
                ->with(['members.subscriptions' => function ($query) {
                    $query->where('expires_at', '>', now());
                }])
                ->whereHas('members.subscriptions', function ($query) {
                    $query->where('expires_at', '>', now());
                })
                ->get();
        });
    }

    public function countActiveSubscriptions(Branch $branch): int
    {
        $cacheKey = "branch.{$branch->id}.active_subscriptions_count";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($branch) {
            return $branch->members
                ->flatMap(fn ($member) =>
                    $member->subscriptions->map(fn ($sub) =>
                        $sub->member_id . '-' . $sub->insurance_id
                    )
                )
                ->unique()
                ->count();
        });
    }

    public function countTotalMembers(Branch $branch): int
    {
        $cacheKey = "branch.{$branch->id}.total_members";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($branch) {
            return $branch->members->count();
        });
    }

    public function calculateSubscriptionRate(Branch $branch): string
    {
        $cacheKey = "branch.{$branch->id}.subscription_rate";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($branch) {
            $totalMembers = $this->countTotalMembers($branch);
            $activeSubs = $this->countActiveSubscriptions($branch);

            return $totalMembers > 0
                ? number_format(($activeSubs / $totalMembers) * 100, 1) . '%'
                : '0%';
        });
    }

    public function countExpiringSoonSubscriptions(Branch $branch): int
    {
        $cacheKey = "branch.{$branch->id}.expiring_soon_subscriptions_count";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($branch) {
            return $branch->members
                ->flatMap(function ($member) {
                    return $member->subscriptions
                        ->filter(function ($subscription) {
                            return $subscription->expires_at >= now()
                                && $subscription->expires_at <= now()->addDays(30);
                        });
                })
                ->count();
        });
    }

    public function getBranchIdsWithLowSubscriptionRate()
    {
        return Cache::remember('branches.low_subscription_rate_ids', $this->cacheTtl, function () {
            return Branch::query()
                ->with(['members.subscriptions' => fn ($q) => $q->where('expires_at', '>', now())])
                ->get()
                ->filter(function ($branch) {
                    $totalMembers = $branch->members->count();
                    $activeSubs = $this->countActiveSubscriptions($branch);
                    $rate = $totalMembers > 0 ? ($activeSubs / $totalMembers) * 100 : 0;
                    return $rate < 60;
                })
                ->pluck('id');
        });
    }

    public function getBranchIdsWithExpiringSoon()
    {
        return Cache::remember('branches.expiring_soon_ids', $this->cacheTtl, function () {
            return Branch::query()
                ->with(['members.subscriptions' => fn ($q) => $q->whereBetween('expires_at', [now(), now()->addDays(30)])])
                ->get()
                ->filter(function ($branch) {
                    return $branch->members
                        ->flatMap(fn ($member) =>
                            $member->subscriptions->filter(fn ($sub) =>
                                $sub->expires_at >= now() && $sub->expires_at <= now()->addDays(30)
                            )
                        )
                        ->isNotEmpty();
                })
                ->pluck('id');
        });
    }
}
