<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\SaleLine> */
class SaleLineFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::factory()->accessory()->create();
        $price = $product->selling_price;

        return [
            'sale_id' => Sale::factory(),
            'sellable_type' => 'product',
            'sellable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $price,
            'unit_cost' => $product->cost,
            'line_discount' => 0,
            'amount' => $price,
        ];
    }
}
