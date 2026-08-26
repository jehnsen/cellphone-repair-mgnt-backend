<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Shift> */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        $openedAt = fake()->dateTimeBetween('-30 days', '-1 hour');
        $closedAt = (clone $openedAt)->modify('+8 hours');
        $expected = fake()->randomFloat(2, 2000, 20000);
        $counted = $expected + fake()->randomFloat(2, -200, 200);

        return [
            'branch_id' => Branch::factory(),
            'cashier_id' => User::factory(),
            'opened_at' => $openedAt,
            'opening_float' => 2000,
            'closed_at' => $closedAt,
            'counted_cash' => $counted,
            'expected_cash' => $expected,
            'variance' => round($counted - $expected, 2),
            'notes' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'closed_at' => null,
            'counted_cash' => null,
            'expected_cash' => null,
            'variance' => null,
        ]);
    }
}
