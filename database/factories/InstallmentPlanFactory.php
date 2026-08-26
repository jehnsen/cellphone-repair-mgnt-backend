<?php

namespace Database\Factories;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\InstallmentPlan> */
class InstallmentPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'principal' => fake()->randomFloat(2, 3000, 20000),
            'downpayment' => fake()->randomFloat(2, 500, 3000),
            'term_months' => fake()->randomElement([3, 6, 12]),
            'schedule_rule' => 'monthly',
            'status' => 'active',
        ];
    }
}
