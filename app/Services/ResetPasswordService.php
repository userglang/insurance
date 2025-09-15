<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ResetPasswordService
{
    /**
     * Handle the password reset.
     *
     * @param  User  $user
     * @return void
     */
    public function reset(User $user)
    {
        $defaultPassword = 'password123';

        // Resetting the password
        $user->update([
            'password' => Hash::make($defaultPassword),
        ]);
    }
}
