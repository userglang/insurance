<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Branch;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Get a random branch or the first one available
        $branch = Branch::inRandomOrder()->first();

        // Manually create 1 admin user
        User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'), // Replace with strong password
            'is_active' => true,
            'branch_id' => $branch?->id, // safe null-check just in case
        ]);

        // Create 4 users via factory and assign random branch_id
        $branches = Branch::pluck('id')->toArray();

        User::factory()
            ->count(4)
            ->create()
            ->each(function ($user) use ($branches) {
                $user->branch_id = collect($branches)->random();
                $user->save();
            });
    }
}
