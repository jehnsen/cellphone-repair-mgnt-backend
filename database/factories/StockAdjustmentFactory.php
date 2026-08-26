<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\StockAdjustment> */
class StockAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'reason_code' => fake()->randomElement(['count_variance', 'damage', 'internal_use', 'sample']),
            'note' => fake()->optional(0.6)->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
