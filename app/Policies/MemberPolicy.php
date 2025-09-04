<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Member;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MemberPolicy
{
    use HandlesAuthorization;

    /**
     * Cache key prefix for policy results
     */
    private const CACHE_PREFIX = 'member_policy';

    /**
     * Cache TTL in seconds (5 minutes)
     */
    private const CACHE_TTL = 300;

    /**
     * Roles that have global access to all members
     */
    private const GLOBAL_ACCESS_ROLES = ['super_admin'];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_member');
    }

    /**
     * Determine whether the user can view the specific member.
     * Super admins can view any member.
     * Other users must have the permission and be in the same branch.
     */
    public function view(User $user, Member $member): bool
    {
        // Early return for global access roles
        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        $canView = $this->checkPermissionWithCache($user, 'view_member')
            && $this->isSameBranch($user, $member);

        if (!$canView) {
            $this->logUnauthorizedAttempt($user, $member, 'view');
        }

        return $canView;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_member');
    }

    /**
     * Determine whether the user can update the specific member.
     * Super admins can update any member.
     * Others must have permission and be in the same branch.
     */
    public function update(User $user, Member $member): bool
    {
        // Early return for global access roles
        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        $canUpdate = $this->checkPermissionWithCache($user, 'update_member')
            && $this->isSameBranch($user, $member);

        if (!$canUpdate) {
            $this->logUnauthorizedAttempt($user, $member, 'update');
        }

        return $canUpdate;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Member $member): bool
    {
        // Super admins can delete any member (except protected ones)
        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        $canDelete = $this->checkPermissionWithCache($user, 'delete_member')
            && $this->isSameBranch($user, $member);

        if (!$canDelete) {
            $this->logUnauthorizedAttempt($user, $member, 'delete');
        }

        return $canDelete;
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_member');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Member $member): bool
    {
        return $user->can('force_delete_member');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_member');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Member $member): bool
    {
        return $user->can('restore_member');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_member');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Member $member): bool
    {
        return $user->can('replicate_member');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_member');
    }




    // =====================================
    // Helper Methods
    // =====================================

    /**
     * Check if user has global access roles
     */
    private function hasGlobalAccess(User $user): bool
    {
        return $user->hasAnyRole(self::GLOBAL_ACCESS_ROLES);
    }

    /**
     * Check if user and member are in the same branch
     */
    private function isSameBranch(User $user, Member $member): bool
    {
        return $user->branch?->branch_number === $member->branch_number;
    }

    /**
     * Check permission with caching
     */
    private function checkPermissionWithCache(User $user, string $permission): bool
    {
        $cacheKey = self::CACHE_PREFIX . ".{$user->id}.{$permission}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $permission) {
            return $user->can($permission);
        });
    }

    /**
     * Log unauthorized access attempts with context
     */
    private function logUnauthorizedAttempt(User $user, ?Member $member, string $action, string $reason = 'Permission denied'): void
    {
        $context = [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_branch' => $user->branch?->branch_number,
            'action' => $action,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ];

        if ($member) {
            $context = array_merge($context, [
                'member_id' => $member->id,
                'member_branch' => $member->branch_number,
                'member_status' => $member->status ?? 'unknown',
            ]);
        }

        Log::warning("Unauthorized member {$action} attempt", $context);
    }
}




