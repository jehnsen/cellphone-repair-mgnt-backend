<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\CommissionRule> */
class CommissionRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'technician_id' => null,
            'role' => 'technician',
            'basis' => 'percent_of_labor',
            'value' => 10,
            'effective_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'effective_to' => null,
        ];
    }
}
