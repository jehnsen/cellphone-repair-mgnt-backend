<?php

namespace Database\Factories;

use App\Models\InstallmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\InstallmentSchedule> */
class InstallmentScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'installment_plan_id' => InstallmentPlan::factory(),
            'due_date' => fake()->dateTimeBetween('now', '+6 months'),
            'amount_due' => fake()->randomFloat(2, 500, 3000),
            'amount_paid' => 0,
            'status' => 'pending',
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => fake()->dateTimeBetween('-60 days', '-1 day'),
            'status' => 'overdue',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_paid' => $attributes['amount_due'],
            'status' => 'paid',
        ]);
    }
}
