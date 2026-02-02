<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MemberStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        /** @var DashboardService $dashboard */
        $dashboard = app(DashboardService::class);

        $stats = $dashboard->getDashboardStats();

        return [
            Stat::make('✅ Active Members', number_format($stats['activeMembers']))
                ->description($this->getActivePercentageDescription($stats['activeMembers'], $stats['totalMembers']))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('❌ Declined Members', number_format($stats['declinedMembers']))
                ->description($this->getDeclinedPercentageDescription($stats['declinedMembers'], $stats['totalMembers']))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('🟡 Pending Applications', number_format($stats['pendingMembers']))
                ->description($this->getPendingDescription($stats['pendingMembers']))
                ->descriptionIcon('heroicon-m-clock')
                ->color($stats['pendingMembers'] > 10 ? 'warning' : 'info'),

            Stat::make('⏰ Expires Soon', number_format($stats['expiringSoon']))
                ->description($this->getExpiresSoonDescription($stats['expiringSoon']))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats['expiringSoon'] > 0 ? 'warning' : 'success'),

            Stat::make('❌ Expired', number_format($stats['expired']))
                ->description($this->getExpiredDescription($stats['expired']))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($stats['expired'] > 0 ? 'danger' : 'success'),

            Stat::make('🔄 Active Subscriptions', number_format($stats['activeSubscriptions']))
                ->description('Members with valid subscriptions')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('success'),

            Stat::make('💰 Revenue This Month', $this->formatCurrency($stats['revenueThisMonth']))
                ->description('Subscription income this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(
                '⚠️ Needs Attention',
                number_format(
                    $stats['pendingMembers'] + $stats['expired'] + $stats['expiringSoon']
                )
            )
                ->description('Pending + Expired + Expiring')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color(
                    ($stats['pendingMembers'] + $stats['expired'] + $stats['expiringSoon']) > 5
                        ? 'warning'
                        : 'gray'
                ),
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return '₱' . number_format($amount, 2);
    }

    private function getActivePercentageDescription(int $active, int $total): string
    {
        return $total > 0 ? round(($active / $total) * 100, 1) . '% of total' : '0%';
    }

    private function getDeclinedPercentageDescription(int $declined, int $total): string
    {
        return $total > 0 ? round(($declined / $total) * 100, 1) . '% declined' : '0%';
    }

    private function getPendingDescription(int $pending): string
    {
        return $pending > 0 ? "$pending pending approval" : 'No pending applications';
    }

    private function getExpiresSoonDescription(int $expiringSoon): string
    {
        return $expiringSoon > 0 ? "$expiringSoon expiring soon" : 'No upcoming expirations';
    }

    private function getExpiredDescription(int $expired): string
    {
        return $expired > 0 ? "$expired expired" : 'All subscriptions are valid';
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
