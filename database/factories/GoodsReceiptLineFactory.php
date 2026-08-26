<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\GoodsReceiptLine> */
class GoodsReceiptLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'goods_receipt_id' => GoodsReceipt::factory(),
            'purchase_order_line_id' => null,
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'unit_cost' => fake()->randomFloat(2, 100, 5000),
            'serialized_unit_id' => null,
        ];
    }
}
