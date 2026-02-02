<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MemberService
{
    /**
     * Get current user's branch number, or null if super admin
     */
    private function getBranchNumber(): ?int
    {
        $user = Auth::user();

        if ($user && $user->hasRole('super_admin')) {
            // Log::info('Super admin detected - no branch filter applied');
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
        return "stats.members.{$prefix}." . ($branch ?? 'all');
    }

    /**
     * Base query scoped to branch (if not super admin)
     */
    private function baseQuery()
    {
        $query = Member::query();

        if ($branchNumber = $this->getBranchNumber()) {
            // Log::info('Filtering members by branch number', ['branch_number' => $branchNumber]);
            $query->where('branch_number', $branchNumber);
        } else {
            // Log::info('No branch number filter applied (super admin or no branch)');
        }

        return $query;
    }

    /**
     * Get member stats in one query (total, active, declined, pending)
     */
    public function getMemberStats(): array
    {
        return Cache::remember($this->buildCacheKey('stats'), now()->addMinutes(60), function () {
            $stats = $this->baseQuery()
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'accepted' AND is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'declined' AND is_active = 1 THEN 1 ELSE 0 END) as declined,
                    SUM(CASE WHEN status = 'pending' AND is_active = 1 THEN 1 ELSE 0 END) as pending
                ")
                ->first();

            return [
                'total' => (int) $stats->total,
                'active' => (int) $stats->active,
                'declined' => (int) $stats->declined,
                'pending' => (int) $stats->pending,
            ];
        });
    }

    /**
     * Deprecated individual count methods for backward compatibility (optional)
     */
    public function totalMembers(): int
    {
        return $this->getMemberStats()['total'];
    }

    public function activeMembers(): int
    {
        return $this->getMemberStats()['active'];
    }

    public function declinedMembers(): int
    {
        return $this->getMemberStats()['declined'];
    }

    public function pendingMembers(): int
    {
        return $this->getMemberStats()['pending'];
    }
}
