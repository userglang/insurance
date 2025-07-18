<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),

            // Format: BR001, BR002, etc.
            'branch_number' => 'BR' . str_pad($this->faker->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),

            // Example: Acme Corp Branch
            'branch_name' => $this->faker->company . ' Branch',

            'address' => $this->faker->address,

            // Format: e.g., MNL, CEB — 3 capital letters
            'code' => strtoupper($this->faker->unique()->lexify('???')),

            // 90% chance to be active
            'is_active' => $this->faker->boolean(90),
        ];
    }
}
