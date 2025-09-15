<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        // Cache password creation date for new users
        if ($user->password) {
            $cacheKey = 'password_changed_' . ($user->id ?? 'temp_' . uniqid());
            Cache::put($cacheKey, now(), now()->addYear());
        }
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Update cache key with actual user ID
        $tempCacheKey = 'password_changed_temp_' . session('temp_user_id', '');
        if (Cache::has($tempCacheKey)) {
            Cache::put('password_changed_' . $user->id, Cache::get($tempCacheKey), now()->addYear());
            Cache::forget($tempCacheKey);
        } else {
            // Set creation date as password change date
            Cache::put('password_changed_' . $user->id, now(), now()->addYear());
        }

        // Check if password was marked as weak during creation
        if (session('temp_password_weak_' . $user->id)) {
            Cache::put('weak_password_' . $user->id, true, now()->addMonth());
            session()->forget('temp_password_weak_' . $user->id);
        }

        // Check for old passwords
        $this->checkPasswordAge($user);
    }

    /**
     * Check if password is older than 3 months and flag for change
     */
    protected function checkPasswordAge(User $user): void
    {
        $passwordChangeDate = Cache::get('password_changed_' . $user->id, $user->updated_at ?? $user->created_at);

        if ($passwordChangeDate) {
            $passwordAge = now()->diffInDays($passwordChangeDate);

            if ($passwordAge > 90) {
                session(['user_' . $user->id . '_must_change_password' => true]);
            }
        }
    }

    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        // Check if password was changed
        if ($user->isDirty('password')) {
            // Update password change date in cache
            Cache::put('password_changed_' . $user->id, now(), now()->addYear());

            // Clear flags when password is changed
            session()->forget('user_' . $user->id . '_must_change_password');
            Cache::forget('weak_password_' . $user->id);

            // Check if new password is weak
            if (session('temp_password_check')) {
                $plainPassword = session('temp_password_check');
                $isWeak = $this->isPasswordWeak($plainPassword);

                if ($isWeak) {
                    Cache::put('weak_password_' . $user->id, true, now()->addMonth());
                }

                // Clear the session
                session()->forget('temp_password_check');
            }
        }
    }

    /**
     * Determine if a password is weak
     */
    protected function isPasswordWeak(string $password): bool
    {
        // Common weak passwords
        $commonWeakPasswords = [
            'password', 'password123', '123456', '123456789', 'qwerty', 'abc123',
            'password1', 'admin', 'letmein', 'welcome', 'monkey', '1234567890',
            'password12', 'password1234', 'admin123', 'root', 'toor', 'pass',
            'test', 'guest', 'user', 'default', 'changeme', 'temp123'
        ];

        // Check against common weak passwords (case insensitive)
        if (in_array(strtolower($password), $commonWeakPasswords)) {
            return true;
        }

        // Check minimum length
        if (strlen($password) < 8) {
            return true;
        }

        // Check for only numbers
        if (ctype_digit($password)) {
            return true;
        }

        // Check for only letters
        if (ctype_alpha($password)) {
            return true;
        }

        // Check for simple patterns
        if (preg_match('/^(.)\1{3,}$/', $password)) { // Repeated characters (aaaa, 1111)
            return true;
        }

        if (preg_match('/^(012|123|234|345|456|567|678|789|890|987|876|765|654|543|432|321|210)+/', $password)) {
            return true; // Sequential patterns
        }

        // Check for keyboard patterns
        $keyboardPatterns = ['qwerty', 'asdf', 'zxcv', '1234', 'abcd'];
        foreach ($keyboardPatterns as $pattern) {
            if (stripos($password, $pattern) !== false && strlen($pattern) >= 4) {
                return true;
            }
        }

        // Require at least 3 of 4 character types for passwords under 12 characters
        if (strlen($password) < 12) {
            $hasLower = preg_match('/[a-z]/', $password);
            $hasUpper = preg_match('/[A-Z]/', $password);
            $hasNumber = preg_match('/[0-9]/', $password);
            $hasSymbol = preg_match('/[^A-Za-z0-9]/', $password);

            $complexity = $hasLower + $hasUpper + $hasNumber + $hasSymbol;

            if ($complexity < 3) {
                return true;
            }
        }

        return false;
    }
}
