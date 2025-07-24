<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MemberStats extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // Cache the expensive queries for 5 minutes
        $cacheKey = 'member_stats_' . now()->format('Y-m-d-H-i');
        $cacheDuration = now()->addMinutes(5);

        $stats = Cache::remember($cacheKey, $cacheDuration, function () {
            return $this->calculateStats();
        });

        return $this->buildStatCards($stats);
    }

    private function calculateStats(): array
    {
        // Single query to get all counts efficiently
        $statusCounts = Member::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get time-based statistics
        $timeStats = $this->getTimeBasedStats();

        // Get subscription-related statistics
        $subscriptionStats = $this->getSubscriptionStats();

        // Calculate totals
        $total = array_sum($statusCounts);
        $accepted = $statusCounts['accepted'] ?? 0;
        $active = Member::where('status', 'accepted')->where('is_active', true)->count();
        $pending = $statusCounts['pending'] ?? 0;
        $declined = $statusCounts['declined'] ?? 0;
        $archived = Member::where('is_active', false)->count();
        $inactive = $total - $active;

        return array_merge([
            'total' => $total,
            'accepted' => $accepted,
            'active' => $active,
            'pending' => $pending,
            'declined' => $declined,
            'archived' => $archived,
            'inactive' => $inactive,
        ], $timeStats, $subscriptionStats);
    }

    private function getTimeBasedStats(): array
    {
        $now = Carbon::now();

        return [
            'new_this_month' => Member::whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->where('is_active', true) // Filter out archived members
                ->count(),
            'new_last_month' => Member::whereMonth('created_at', $now->subMonth()->month)
                ->whereYear('created_at', $now->year)
                ->where('is_active', true) // Filter out archived members
                ->count(),
            'new_this_week' => Member::whereBetween('created_at', [
                $now->startOfWeek()->toDateString(),
                $now->endOfWeek()->toDateString()
            ])
                ->where('is_active', true) // Filter out archived members
                ->count(),
            'new_today' => Member::whereDate('created_at', $now->toDateString())
                ->where('is_active', true) // Filter out archived members
                ->count(),
        ];
    }

    private function getSubscriptionStats(): array
    {
        $now = Carbon::now();
        $soonThreshold = $now->copy()->addDays(30); // 30 days from now

        return [
            'expires_soon' => Member::where('is_active', true) // Filter out archived members
            ->whereHas('latestSubscription', function ($query) use ($now, $soonThreshold) {
                $query->where('expires_at', '>', $now)
                    ->where('expires_at', '<=', $soonThreshold);
            })->count(),

            'expired' => Member::where('is_active', true) // Filter out archived members
            ->whereHas('latestSubscription', function ($query) use ($now) {
                $query->where('expires_at', '<', $now);
            })->count(),

            'recommended' => Member::where('status', 'accepted')
                ->where('is_active', true) // Already filtered, but keeping for clarity
                ->whereHas('subscriptions', function ($query) use ($now) {
                    $query->where('expires_at', '>', $now);
                })
                ->whereDoesntHave('subscriptions', function ($query) use ($now) {
                    // Members who don't have recent renewals (last 6 months)
                    $query->where('created_at', '>', $now->copy()->subMonths(6))
                        ->where('expires_at', '>', $now->copy()->addMonths(6));
                })
                ->count(),

            'active_subscriptions' => Subscription::select('member_id', 'insurance_id')
                ->where('expires_at', '>', $now)
                ->whereHas('member', function ($query) {
                    $query->where('is_active', true); // Filter out archived members
                })
                ->groupBy('member_id', 'insurance_id')
                ->get()
                ->count(),

            'subscription_revenue_this_month' => Subscription::whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->sum('amount') ?? 0,
        ];
    }

    private function buildStatCards(array $stats): array
    {
        $growthPercentage = $this->calculateGrowthPercentage(
            $stats['new_this_month'],
            $stats['new_last_month']
        );

        // Calculate filtered declined members (excluding archived)
        $declinedActive = Member::where('status', 'declined')
            ->where('is_active', true)
            ->count();

        // Calculate filtered pending applications (excluding archived)
        $pendingActive = Member::where('status', 'pending')
            ->where('is_active', true)
            ->count();

        return [
            // Total Members with trend
//            Stat::make('👥 Total Members', number_format($stats['total']))
//                ->description('All registered members')
//                ->descriptionIcon('heroicon-m-users')
//                ->color('gray')
//                ->chart($this->getMonthlyTrendChart()),

            // Active Members with percentage
            Stat::make('✅ Active Members', number_format($stats['active']))
                ->description($this->getActivePercentageDescription($stats['active'], $stats['total']))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([$stats['active'], $stats['inactive']]),

            // Declined Members (filtered)
            Stat::make('❌ Declined Members', number_format($declinedActive))
                ->description($this->getDeclinedPercentageDescription($declinedActive, $stats['total']))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->chart($this->getStatusChart($stats)),

            // Pending Applications with urgency indicator (filtered)
            Stat::make('🟡 Pending Applications', number_format($pendingActive))
                ->description($this->getPendingDescription($pendingActive))
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingActive > 10 ? 'warning' : 'info'),

            // Expires Soon - New (filtered)
            Stat::make('⏰ Expires Soon', number_format($stats['expires_soon']))
                ->description($this->getExpiresSoonDescription($stats['expires_soon']))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats['expires_soon'] > 0 ? 'warning' : 'success'),

            // Expired - New (filtered)
            Stat::make('❌ Expired', number_format($stats['expired']))
                ->description($this->getExpiredDescription($stats['expired']))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($stats['expired'] > 0 ? 'danger' : 'success'),

            // Recommended for Renewal - New (filtered)
            Stat::make('💡 Recommended', number_format($stats['recommended']))
                ->description($this->getRecommendedDescription($stats['recommended']))
                ->descriptionIcon('heroicon-m-star')
                ->color($stats['recommended'] > 0 ? 'info' : 'gray'),

            // New This Month with growth indicator (filtered)
            Stat::make('🆕 New This Month', number_format($stats['new_this_month']))
                ->description($this->getGrowthDescription($growthPercentage))
                ->descriptionIcon($growthPercentage >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($growthPercentage >= 0 ? 'success' : 'danger')
                ->chart($this->getWeeklyNewMembersChart()),

            // Activity Summary
            Stat::make('📊 Activity Rate', $this->getActivityRate($stats['active'], $stats['total']))
                ->description('Active vs Total members')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($this->getActivityRateColor($stats['active'], $stats['total']))
                ->chart([$stats['active'], $stats['inactive']]),

            // Active Subscriptions - Enhanced (filtered)
            Stat::make('🔄 Active Subscriptions', number_format($stats['active_subscriptions']))
                ->description('Currently valid subscriptions')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('success')
                ->chart($this->getSubscriptionTrendChart()),

            // Revenue This Month - New (filtered)
            Stat::make('💰 Revenue This Month', '₱' . number_format($stats['subscription_revenue_this_month'], 2))
                ->description('Subscription income this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            // Weekly Summary (filtered)
            Stat::make('📅 This Week', number_format($stats['new_this_week']))
                ->description('New members this week')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            // Status Overview - Enhanced (filtered)
            Stat::make('⚠️ Needs Attention', number_format($pendingActive + $stats['expired'] + $stats['expires_soon']))
                ->description('Pending + Expired + Expiring')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($pendingActive + $stats['expired'] + $stats['expires_soon'] > 5 ? 'warning' : 'gray'),
        ];
    }

    private function calculateGrowthPercentage(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function getDeclinedPercentageDescription(int $declined, int $total): string
    {
        if ($total === 0) return 'No members yet';

        $percentage = round(($declined / $total) * 100, 1);
        return "{$percentage}% of all members";
    }

    private function getActivePercentageDescription(int $active, int $total): string
    {
        if ($total === 0) return 'No members yet';

        $percentage = round(($active / $total) * 100, 1);
        return "{$percentage}% are active (accepted + not archived)";
    }

    private function getPendingDescription(int $pending): string
    {
        if ($pending === 0) return 'No pending applications';
        if ($pending === 1) return '1 application awaiting review';
        if ($pending <= 5) return 'Low priority queue';
        if ($pending <= 10) return 'Moderate queue';

        return 'High priority - needs attention!';
    }

    private function getExpiresSoonDescription(int $expiresSoon): string
    {
        if ($expiresSoon === 0) return 'No subscriptions expiring soon';
        if ($expiresSoon === 1) return '1 subscription expires within 30 days';
        if ($expiresSoon <= 5) return 'Few subscriptions expiring soon';
        if ($expiresSoon <= 10) return 'Several subscriptions need renewal';

        return 'Many subscriptions expiring - take action!';
    }

    private function getExpiredDescription(int $expired): string
    {
        if ($expired === 0) return 'No expired subscriptions';
        if ($expired === 1) return '1 expired subscription';
        if ($expired <= 5) return 'Few expired subscriptions';
        if ($expired <= 10) return 'Several expired subscriptions';

        return 'Many expired subscriptions!';
    }

    private function getRecommendedDescription(int $recommended): string
    {
        if ($recommended === 0) return 'No renewal recommendations';
        if ($recommended === 1) return '1 member recommended for renewal';
        if ($recommended <= 5) return 'Few members ready for renewal';
        if ($recommended <= 10) return 'Several members ready for renewal';

        return 'Many members ready for renewal!';
    }

    private function getGrowthDescription(float $percentage): string
    {
        if ($percentage > 0) {
            return "+{$percentage}% vs last month";
        } elseif ($percentage < 0) {
            return "{$percentage}% vs last month";
        }

        return 'No change from last month';
    }

    private function getActivityRate(int $active, int $total): string
    {
        if ($total === 0) return '0%';

        $rate = round(($active / $total) * 100, 1);
        return "{$rate}%";
    }

    private function getActivityRateColor(int $active, int $total): string
    {
        if ($total === 0) return 'gray';

        $rate = ($active / $total) * 100;

        return match (true) {
            $rate >= 80 => 'success',
            $rate >= 60 => 'info',
            $rate >= 40 => 'warning',
            default => 'danger',
        };
    }

    private function getStatusChart(array $stats): array
    {
        return [
            $stats['accepted'],
            $stats['pending'],
            $stats['declined'],
            $stats['archived'],
        ];
    }

    private function getMonthlyTrendChart(): array
    {
        // Get last 6 months of member registrations (filtered)
        return Cache::remember('member_monthly_trend', now()->addHours(6), function () {
            $months = collect();

            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $count = Member::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->where('is_active', true) // Filter out archived members
                    ->count();
                $months->push($count);
            }

            return $months->toArray();
        });
    }

    private function getWeeklyNewMembersChart(): array
    {
        // Get last 4 weeks of new members (filtered)
        return Cache::remember('member_weekly_trend', now()->addHours(2), function () {
            $weeks = collect();

            for ($i = 3; $i >= 0; $i--) {
                $startOfWeek = Carbon::now()->subWeeks($i)->startOfWeek();
                $endOfWeek = Carbon::now()->subWeeks($i)->endOfWeek();

                $count = Member::whereBetween('created_at', [
                    $startOfWeek->toDateString(),
                    $endOfWeek->toDateString()
                ])
                    ->where('is_active', true) // Filter out archived members
                    ->count();

                $weeks->push($count);
            }

            return $weeks->toArray();
        });
    }

    private function getSubscriptionTrendChart(): array
    {
        // Get subscription trend for last 6 months (filtered)
        return Cache::remember('subscription_trend', now()->addHours(4), function () {
            $months = collect();

            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $count = Subscription::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->whereHas('member', function ($query) {
                        $query->where('is_active', true); // Filter out archived members
                    })
                    ->count();
                $months->push($count);
            }

            return $months->toArray();
        });
    }

    protected function getColumns(): int
    {
        return 4; // Display 4 cards per row on larger screens
    }

//    public static function canView(): bool
//    {
//        return auth()->user()?->can('view_member_stats') ?? true;
//    }
}
