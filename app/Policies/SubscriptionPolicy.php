<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SubscriptionPolicy
{
    use HandlesAuthorization;

    /**
     * Cache key prefix for policy results
     */
    private const CACHE_PREFIX = 'subscription_policy';

    /**
     * Cache TTL in seconds (5 minutes)
     */
    private const CACHE_TTL = 300;

    /**
     * Roles that have global access to all subscriptions
     */
    private const GLOBAL_ACCESS_ROLES = ['super_admin'];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->checkPermissionWithCache($user, 'view_any_subscription');
    }

    /**
     * Determine whether the user can view the subscription.
     */
    public function view(User $user, Subscription $subscription): bool
    {
        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        $canView = $this->checkPermissionWithCache($user, 'view_subscription')
            && $this->isSameBranch($user, $subscription);

        if (! $canView) {
            $this->logUnauthorizedAttempt($user, $subscription, 'view');
        }

        return $canView;
    }

    /**
     * Determine whether the user can create subscriptions.
     */
    public function create(User $user): bool
    {
        return $this->checkPermissionWithCache($user, 'create_subscription');
    }

    /**
     * Determine whether the user can update the subscription.
     */
    public function update(User $user, Subscription $subscription): bool
    {
        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        $canUpdate = $this->checkPermissionWithCache($user, 'update_subscription')
            && $this->isSameBranch($user, $subscription);

        if (! $canUpdate) {
            $this->logUnauthorizedAttempt($user, $subscription, 'update');
        }

        return $canUpdate;
    }

    /**
     * Determine whether the user can delete the subscription.
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        // Global access (super_admin)
        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        // ❌ Block immediately if not same branch
        if (! $this->isSameBranch($user, $subscription)) {
            $this->logUnauthorizedAttempt(
                $user,
                $subscription,
                'delete',
                'Different branch'
            );

            return false;
        }

        // Permission check
        $canDelete = $this->checkPermissionWithCache($user, 'delete_subscription');

        if (! $canDelete) {
            $this->logUnauthorizedAttempt($user, $subscription, 'delete');
        }

        return $canDelete;
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $this->checkPermissionWithCache($user, 'delete_any_subscription');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Subscription $subscription): bool
    {
        return $this->checkPermissionWithCache($user, 'force_delete_subscription');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $this->checkPermissionWithCache($user, 'force_delete_any_subscription');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Subscription $subscription): bool
    {
        return $this->checkPermissionWithCache($user, 'restore_subscription');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $this->checkPermissionWithCache($user, 'restore_any_subscription');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Subscription $subscription): bool
    {
        return $this->checkPermissionWithCache($user, 'replicate_subscription');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $this->checkPermissionWithCache($user, 'reorder_subscription');
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
     * Check if user and subscription belong to the same branch
     *
     * Assumes:
     * $subscription->member->branch_number exists
     */
    private function isSameBranch(User $user, Subscription $subscription): bool
    {
        return $user->branch?->branch_number
            === $subscription->member?->branch_number;
    }

    /**
     * Check permission with caching
     */
    private function checkPermissionWithCache(User $user, string $permission): bool
    {
        $cacheKey = self::CACHE_PREFIX . ".{$user->id}.{$permission}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () =>
            $user->can($permission)
        );
    }

    /**
     * Log unauthorized access attempts with context
     */
    private function logUnauthorizedAttempt(
        User $user,
        ?Subscription $subscription,
        string $action,
        string $reason = 'Permission denied'
    ): void {
        $context = [
            'user_id'      => $user->id,
            'user_email'  => $user->email,
            'user_branch' => $user->branch?->branch_number,
            'action'      => $action,
            'reason'      => $reason,
            'timestamp'   => now()->toISOString(),
            'ip_address'  => request()?->ip(),
            'user_agent'  => request()?->userAgent(),
        ];

        if ($subscription) {
            $context += [
                'subscription_id' => $subscription->id,
                'member_id'       => $subscription->member_id,
                'member_branch'   => $subscription->member?->branch_number,
                'status'          => $subscription->status ?? 'unknown',
            ];
        }

        Log::warning("Unauthorized subscription {$action} attempt", $context);
    }
}
