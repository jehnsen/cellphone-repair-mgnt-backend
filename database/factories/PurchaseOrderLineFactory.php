<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PurchaseOrderLine> */
class PurchaseOrderLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'ordered_qty' => fake()->numberBetween(1, 50),
            'received_qty' => 0,
            'unit_cost' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}
