<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\RateLimiter;

class RequiredChangePassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {

        // Skip middleware if user is not authenticated
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Skip if user is already on the change-password page
        if ($request->routeIs('filament.main.pages.change-password')) {
            return $next($request);
        }

        // Skip if it's the logout route
        if ($request->routeIs('filament.main.auth.logout')) {
            return $next($request);
        }

        // Rate limit the request to prevent brute force attacks on login and password change
        if ($this->isRateLimited($request)) {
            return response()->json(['error' => 'Too many requests. Please try again later.'], 429);
        }

        // Force all authenticated users with default passwords to change them
        if ($this->hasDefaultPassword($user)) {
            return redirect()->route('filament.main.pages.change-password')
                ->with('warning', 'You must change your default password before continuing.');
        }

        // Check if password is older than 90 days and force password change
        if ($this->isPasswordOld($user)) {
            return redirect()->route('filament.main.pages.change-password')
                ->with('warning', 'Your password is older than 90 days. Please change it.');
        }

        // Proceed with the request
        return $next($request);
    }

    /**
     * Check if the request is rate-limited.
     *
     * @param \Illuminate\Http\Request $request
     * @return bool
     */
    private function isRateLimited(Request $request): bool
    {
        $key = 'request:' . $request->ip();
        return RateLimiter::tooManyAttempts($key, 5); // Allow only 5 requests per minute
    }

    /**
     * Check if the user has a default password.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    private function hasDefaultPassword($user): bool
    {
        $defaultPasswords = config('security.default_passwords');

        foreach ($defaultPasswords as $defaultPassword) {
            $defaultPassword = trim($defaultPassword);

            if (Hash::check($defaultPassword, $user->password)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Check if the user's password is older than 90 days.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    private function isPasswordOld($user): bool
    {
        $passwordLastChangedAt = $user->updated_at;  // You can use 'password_last_changed' if it's custom
        $passwordAgeInDays = $passwordLastChangedAt->diffInDays(Carbon::now());

        // If password is older than 90 days, force password change
        return $passwordAgeInDays > 90;
    }
}
