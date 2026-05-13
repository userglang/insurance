<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class RequiredChangePassword
{
    private const PASSWORD_MAX_AGE_DAYS = 90;
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const CACHE_TTL = 86400; // 24 hours
    private const CHANGE_PASSWORD_ROUTE = 'filament.main.pages.change-password';
    private const LOGOUT_ROUTE = 'filament.main.auth.logout';

    private const EXCLUDED_ROUTES = [
        self::CHANGE_PASSWORD_ROUTE,
        self::LOGOUT_ROUTE,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check() || $this->isExcludedRoute($request)) {
            return $next($request);
        }

        if ($this->isRateLimited($request)) {
            return response()->json(
                ['error' => 'Too many requests. Please try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        /** @var User $user */
        $user = Auth::user();

        $reason = $this->getPasswordChangeReason($user);

        if ($reason !== null) {
            return $this->redirectToPasswordChange($reason);
        }

        return $next($request);
    }

    private function isExcludedRoute(Request $request): bool
    {
        return $request->routeIs(...self::EXCLUDED_ROUTES);
    }

    private function isRateLimited(Request $request): bool
    {
        return RateLimiter::tooManyAttempts(
            'request:' . $request->ip(),
            self::RATE_LIMIT_MAX_ATTEMPTS
        );
    }

    /**
     * Returns the redirect reason if a password change is required, or null if not.
     * Consolidates all checks in one place to avoid redundant cache hits.
     */
    private function getPasswordChangeReason(User $user): ?string
    {
        if ($this->hasDefaultPassword($user)) {
            return 'You must change your default password before continuing.';
        }

        if ($this->isPasswordExpired($user)) {
            return sprintf(
                'Your password is older than %d days. Please change it.',
                self::PASSWORD_MAX_AGE_DAYS
            );
        }

        return null;
    }

    /**
     * Cached for 24 hours per user.
     * Cache key includes a password fingerprint so it auto-invalidates on password change.
     */
    private function hasDefaultPassword(User $user): bool
    {
        $cacheKey = "default_password_check:{$user->id}:" . substr($user->password, -8);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return collect(config('security.default_passwords', []))
                ->map(fn(string $p) => trim($p))
                ->contains(fn(string $password) => Hash::check($password, $user->password));
        });
    }

    /**
     * Cached for 24 hours per user.
     * Must call Cache::forget("password_expired_check:{$user->id}") after a password change.
     */
    private function isPasswordExpired(User $user): bool
    {
        $cacheKey = "password_expired_check:{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            $lastChanged = $user->password_changed_at ?? $user->updated_at;
            return now()->diffInDays($lastChanged) > self::PASSWORD_MAX_AGE_DAYS;
        });
    }

    private function redirectToPasswordChange(string $reason): Response
    {
        return redirect()
            ->route(self::CHANGE_PASSWORD_ROUTE)
            ->with('warning', $reason);
    }
}
