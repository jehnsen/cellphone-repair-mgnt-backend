<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\RefurbJob;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\RefurbJobLine> */
class RefurbJobLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'refurb_job_id' => RefurbJob::factory(),
            'product_id' => Product::factory()->part(),
            'stock_movement_id' => StockMovement::factory(),
            'quantity' => 1,
            'unit_cost' => fake()->randomFloat(2, 100, 2000),
        ];
    }
}
