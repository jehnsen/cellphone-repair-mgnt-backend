<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\StockLevel> */
class StockLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'branch_id' => Branch::factory(),
            'on_hand_qty' => fake()->numberBetween(0, 100),
            'reserved_qty' => 0,
            'updated_at' => now(),
        ];
    }
}
