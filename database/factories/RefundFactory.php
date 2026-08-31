<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Refund> */
class RefundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'reason_code' => fake()->randomElement(['defective', 'wrong_item', 'customer_changed_mind']),
            'refund_method' => 'cash',
            'total_amount' => 0,
            'processed_by' => User::factory(),
        ];
    }
}
