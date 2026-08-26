<?php

namespace Database\Factories;

use App\Models\Refund;
use App\Models\SaleLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\RefundLine> */
class RefundLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'refund_id' => Refund::factory(),
            'sale_line_id' => SaleLine::factory(),
            'quantity' => 1,
            'amount' => fake()->randomFloat(2, 100, 3000),
            'restock_behavior' => 'restock',
        ];
    }
}
