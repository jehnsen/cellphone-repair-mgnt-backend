<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\StockMovement> */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 20);

        return [
            'product_id' => Product::factory(),
            'branch_id' => Branch::factory(),
            'serialized_unit_id' => null,
            'quantity' => $qty,
            'unit_cost' => fake()->randomFloat(2, 100, 5000),
            'movement_type' => 'receipt',
            'reference_type' => null,
            'reference_id' => null,
            'reason_code' => null,
            'actor_id' => User::factory(),
            'balance_after' => $qty,
            'occurred_at' => now(),
        ];
    }
}
