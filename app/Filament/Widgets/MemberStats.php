<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MemberStats extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '30s';
    protected static bool $isLazy = false;

    /**
     * Check if the current user can view this widget
     */
    public static function canView(): bool
    {
        return Auth::check() && (
            Auth::user()->hasRole('super_admin') ||
            Auth::user()->branch_id !== null
        );
    }

    /**
     * Get the user's branch filter - returns branch_number for filtering
     */
    private function getBranchFilter(): ?string
    {
        $user = Auth::user();

        // Super admin can see all data
        if ($user->hasRole('super_admin')) {
            return null;
        }

        // Get branch_number from user's branch relationship
        if ($user->branch_id && $user->branch) {
            return $user->branch->branch_number;
        }

        return null;
    }

    /**
     * Get cache key based on user role and branch
     */
    private function getCacheKey(): string
    {
        $user = Auth::user();
        $branchNumber = $user->hasRole('super_admin') ? 'all' : ($this->getBranchFilter() ?? 'no_branch');

        return sprintf(
            'member_stats_v3_%s_%s_%d_%s',
            now()->format('Y-m-d-H'),
            floor(now()->minute / 15),
            config('app.cache_version', 1),
            $branchNumber
        );
    }

    protected function getStats(): array
    {
        $cacheKey = $this->getCacheKey();
        $cacheDuration = now()->addMinutes(15);

        $stats = Cache::remember($cacheKey, $cacheDuration, function () {
            return $this->calculateStatsOptimized();
        });

        return $this->buildStatCards($stats);
    }

    private function calculateStatsOptimized(): array
    {
        $now = Carbon::now();

        try {
            // Get statistics with proper error handling and branch filtering
            $memberStats = $this->getMemberStatsInOneQuery($now);
            $subscriptionStats = $this->getSubscriptionStatsInOneQuery($now);

            return array_merge($memberStats, $subscriptionStats);
        } catch (\Exception $e) {
            // Log error and return safe defaults
            Log::error('MemberStats calculation failed: ' . $e->getMessage());
            return $this->getDefaultStats();
        }
    }

    private function getMemberStatsInOneQuery(Carbon $now): array
    {
        $branchNumber = $this->getBranchFilter();

        // Build query with branch filtering
        $query = Member::selectRaw("
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted,
            COUNT(CASE WHEN status = 'accepted' AND is_active = 1 THEN 1 END) as active,
            COUNT(CASE WHEN status = 'pending' AND is_active = 1 THEN 1 END) as pending_active,
            COUNT(CASE WHEN status = 'declined' AND is_active = 1 THEN 1 END) as declined_active,
            COUNT(CASE WHEN is_active = 0 THEN 1 END) as archived,
            COUNT(CASE WHEN DATE(created_at) = ? AND is_active = 1 THEN 1 END) as new_today
        ");

        // Apply branch filter if not super_admin
        if ($branchNumber !== null) {
            $query->where('branch_number', $branchNumber);
        }

        $result = $query->addBinding($now->toDateString(), 'select')->first();

        if (!$result) {
            return $this->getDefaultMemberStats();
        }

        // Convert to array and calculate derived values safely
        $stats = $result->toArray();
        $stats['inactive'] = max(0, ($stats['total'] ?? 0) - ($stats['active'] ?? 0));

        // Validate data integrity
        return $this->validateMemberStats($stats);
    }

    private function getSubscriptionStatsInOneQuery(Carbon $now): array
    {
        $branchNumber = $this->getBranchFilter();

        // Build base query with branch filtering
        $baseQuery = DB::table('members as m')->where('m.is_active', 1);

        // Apply branch filter if not super_admin
        if ($branchNumber !== null) {
            $baseQuery->where('m.branch_number', $branchNumber);
        }

        // Get subscription statistics using your approach - latest subscription per member
        $subscriptionResult = $baseQuery->selectRaw("
                COUNT(CASE WHEN (SELECT expires_at FROM subscriptions WHERE member_id = m.id ORDER BY expires_at DESC LIMIT 1) > NOW() THEN 1 END) as active_subscriptions,
                COUNT(CASE WHEN (SELECT expires_at FROM subscriptions WHERE member_id = m.id ORDER BY expires_at DESC LIMIT 1) < NOW() THEN 1 END) as expired,
                COUNT(CASE WHEN (SELECT expires_at FROM subscriptions WHERE member_id = m.id ORDER BY expires_at DESC LIMIT 1) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expires_soon
            ")
            ->first();

        // Get revenue for current month with branch filtering
        $revenueQuery = DB::table('subscriptions as s')
            ->join('members as m', 's.member_id', '=', 'm.id')
            ->whereYear('s.created_at', $now->year)
            ->whereMonth('s.created_at', $now->month);

        // Apply branch filter for revenue if not super_admin
        if ($branchNumber !== null) {
            $revenueQuery->where('m.branch_number', $branchNumber);
        }

        $revenueResult = $revenueQuery->selectRaw('COALESCE(SUM(s.amount), 0) as revenue_this_month')->first();

        if (!$subscriptionResult) {
            return $this->getDefaultSubscriptionStats();
        }

        $stats = (array) $subscriptionResult;
        $stats['revenue_this_month'] = $revenueResult->revenue_this_month ?? 0;

        return $this->validateSubscriptionStats($stats);
    }

    private function buildStatCards(array $stats): array
    {
        // Sanitize all numeric values
        $stats = array_map(function ($value) {
            return is_numeric($value) ? (int) $value : 0;
        }, $stats);

        $branchContext = $this->getBranchContext();

        return [
            // Active Members with percentage
            Stat::make('✅ Active Members', number_format($stats['active']))
                ->description($this->getActivePercentageDescription($stats['active'], $stats['total']) . $branchContext)
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([$stats['active'], $stats['inactive']]),

            // Declined Members
            Stat::make('❌ Declined Members', number_format($stats['declined_active']))
                ->description($this->getDeclinedPercentageDescription($stats['declined_active'], $stats['total']) . $branchContext)
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            // Pending Applications
            Stat::make('🟡 Pending Applications', number_format($stats['pending_active']))
                ->description($this->getPendingDescription($stats['pending_active']) . $branchContext)
                ->descriptionIcon('heroicon-m-clock')
                ->color($stats['pending_active'] > 10 ? 'warning' : 'info'),

            // Expires Soon
            Stat::make('⏰ Expires Soon', number_format($stats['expires_soon']))
                ->description($this->getExpiresSoonDescription($stats['expires_soon']) . $branchContext)
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats['expires_soon'] > 0 ? 'warning' : 'success'),

            // Expired
            Stat::make('❌ Expired', number_format($stats['expired']))
                ->description($this->getExpiredDescription($stats['expired']) . $branchContext)
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($stats['expired'] > 0 ? 'danger' : 'success'),

            // Active Subscriptions - Based on latest subscription per member
            Stat::make('🔄 Active Subscriptions', number_format($stats['active_subscriptions']))
                ->description('Members with valid subscriptions' . $branchContext)
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('success')
                ->chart($this->getSubscriptionTrendChartOptimized()),

            // Revenue This Month - Sanitized currency display
            Stat::make('💰 Revenue This Month', $this->formatCurrency($stats['revenue_this_month']))
                ->description('Subscription income this month' . $branchContext)
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            // Status Overview
            Stat::make('⚠️ Needs Attention', number_format(
                $stats['pending_active'] + $stats['expired'] + $stats['expires_soon']
            ))
                ->description('Pending + Expired + Expiring' . $branchContext)
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color(($stats['pending_active'] + $stats['expired'] + $stats['expires_soon']) > 5 ? 'warning' : 'gray'),
        ];
    }

    /**
     * Get branch context for descriptions
     */
    private function getBranchContext(): string
    {
        $user = Auth::user();

        if ($user->hasRole('super_admin')) {
            return ' (All Branches)';
        }

        return '';
    }

    // Security and validation helper methods
    private function validateMemberStats(array $stats): array
    {
        $defaults = $this->getDefaultMemberStats();

        foreach ($defaults as $key => $defaultValue) {
            if (!isset($stats[$key]) || !is_numeric($stats[$key]) || $stats[$key] < 0) {
                $stats[$key] = $defaultValue;
            }
        }

        return $stats;
    }

    private function validateSubscriptionStats(array $stats): array
    {
        $defaults = $this->getDefaultSubscriptionStats();

        foreach ($defaults as $key => $defaultValue) {
            if (!isset($stats[$key]) || !is_numeric($stats[$key]) || $stats[$key] < 0) {
                $stats[$key] = $defaultValue;
            }
        }

        return $stats;
    }

    private function getDefaultStats(): array
    {
        return array_merge($this->getDefaultMemberStats(), $this->getDefaultSubscriptionStats());
    }

    private function getDefaultMemberStats(): array
    {
        return [
            'total' => 0,
            'accepted' => 0,
            'active' => 0,
            'pending_active' => 0,
            'declined_active' => 0,
            'archived' => 0,
            'new_today' => 0,
            'inactive' => 0,
        ];
    }

    private function getDefaultSubscriptionStats(): array
    {
        return [
            'expires_soon' => 0,
            'expired' => 0,
            'active_subscriptions' => 0,
            'revenue_this_month' => 0,
        ];
    }

    // Safe currency formatting
    private function formatCurrency(float $amount): string
    {
        // Sanitize amount and prevent display of extremely large numbers
        $sanitizedAmount = max(0, min($amount, 999999999.99));
        return '₱' . number_format($sanitizedAmount, 2);
    }

    // Description methods with input validation
    private function getDeclinedPercentageDescription(int $declined, int $total): string
    {
        if ($total === 0) return 'No members yet';

        $declined = max(0, $declined);
        $total = max(1, $total);
        $percentage = round(($declined / $total) * 100, 1);

        return "{$percentage}% of all members";
    }

    private function getActivePercentageDescription(int $active, int $total): string
    {
        if ($total === 0) return 'No members yet';

        $active = max(0, $active);
        $total = max(1, $total);
        $percentage = round(($active / $total) * 100, 1);

        return "{$percentage}% are active (accepted + not archived)";
    }

    private function getPendingDescription(int $pending): string
    {
        $pending = max(0, $pending);

        return match (true) {
            $pending === 0 => 'No pending applications',
            $pending === 1 => '1 application awaiting review',
            $pending <= 5 => 'Low priority queue',
            $pending <= 10 => 'Moderate queue',
            default => 'High priority - needs attention!'
        };
    }

    private function getExpiresSoonDescription(int $expiresSoon): string
    {
        $expiresSoon = max(0, $expiresSoon);

        return match (true) {
            $expiresSoon === 0 => 'No subscriptions expiring soon',
            $expiresSoon === 1 => '1 subscription expires within 30 days',
            $expiresSoon <= 5 => 'Few subscriptions expiring soon',
            $expiresSoon <= 10 => 'Several subscriptions need renewal',
            default => 'Many subscriptions expiring - take action!'
        };
    }

    private function getExpiredDescription(int $expired): string
    {
        $expired = max(0, $expired);

        return match (true) {
            $expired === 0 => 'No expired subscriptions',
            $expired === 1 => '1 expired subscription',
            $expired <= 5 => 'Few expired subscriptions',
            $expired <= 10 => 'Several expired subscriptions',
            default => 'Many expired subscriptions!'
        };
    }

    private function getSubscriptionTrendChartOptimized(): array
    {
        $branchNumber = $this->getBranchFilter();
        $cacheKey = 'subscription_trend_v3_' . ($branchNumber ?? 'all');

        return Cache::remember($cacheKey, now()->addHours(4), function () use ($branchNumber) {
            try {
                // Build query with branch filtering
                $query = DB::table('subscriptions as s')
                    ->join('members as m', 's.member_id', '=', 'm.id')
                    ->where('s.created_at', '>=', now()->subMonths(6));

                // Apply branch filter if not super_admin
                if ($branchNumber !== null) {
                    $query->where('m.branch_number', $branchNumber);
                }

                // Get subscription trend based on creation dates (new subscriptions per month)
                $result = $query->selectRaw("
                        COUNT(CASE WHEN YEAR(s.created_at) = ? AND MONTH(s.created_at) = ? THEN 1 END) as month_5,
                        COUNT(CASE WHEN YEAR(s.created_at) = ? AND MONTH(s.created_at) = ? THEN 1 END) as month_4,
                        COUNT(CASE WHEN YEAR(s.created_at) = ? AND MONTH(s.created_at) = ? THEN 1 END) as month_3,
                        COUNT(CASE WHEN YEAR(s.created_at) = ? AND MONTH(s.created_at) = ? THEN 1 END) as month_2,
                        COUNT(CASE WHEN YEAR(s.created_at) = ? AND MONTH(s.created_at) = ? THEN 1 END) as month_1,
                        COUNT(CASE WHEN YEAR(s.created_at) = ? AND MONTH(s.created_at) = ? THEN 1 END) as current_month
                    ")
                    ->addBinding(now()->subMonths(5)->year, 'select')
                    ->addBinding(now()->subMonths(5)->month, 'select')
                    ->addBinding(now()->subMonths(4)->year, 'select')
                    ->addBinding(now()->subMonths(4)->month, 'select')
                    ->addBinding(now()->subMonths(3)->year, 'select')
                    ->addBinding(now()->subMonths(3)->month, 'select')
                    ->addBinding(now()->subMonths(2)->year, 'select')
                    ->addBinding(now()->subMonths(2)->month, 'select')
                    ->addBinding(now()->subMonths(1)->year, 'select')
                    ->addBinding(now()->subMonths(1)->month, 'select')
                    ->addBinding(now()->year, 'select')
                    ->addBinding(now()->month, 'select')
                    ->first();

                if (!$result) {
                    return [0, 0, 0, 0, 0, 0];
                }

                return [
                    max(0, $result->month_5 ?? 0),
                    max(0, $result->month_4 ?? 0),
                    max(0, $result->month_3 ?? 0),
                    max(0, $result->month_2 ?? 0),
                    max(0, $result->month_1 ?? 0),
                    max(0, $result->current_month ?? 0),
                ];
            } catch (\Exception $e) {
                Log::error('Subscription trend chart failed: ' . $e->getMessage());
                return [0, 0, 0, 0, 0, 0];
            }
        });
    }

    protected function getColumns(): int
    {
        return 4;
    }

    /**
     * Clear cache for this widget (useful for testing or manual refresh)
     */
    public function clearCache(): void
    {
        $user = Auth::user();
        $branchNumber = $user->hasRole('super_admin') ? 'all' : ($this->getBranchFilter() ?? 'no_branch');

        // Clear member stats cache
        $pattern = "member_stats_v3_*_{$branchNumber}";
        Cache::forget($pattern);

        // Clear subscription trend cache
        Cache::forget("subscription_trend_v3_{$branchNumber}");
    }
}
