<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\StockAdjustmentLine> */
class StockAdjustmentLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'product_id' => Product::factory(),
            'serialized_unit_id' => null,
            'quantity_delta' => fake()->randomElement([-5, -2, -1, 1, 2, 5]),
            'unit_cost' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}
