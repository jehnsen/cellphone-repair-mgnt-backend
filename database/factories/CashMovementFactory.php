<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\CashMovement> */
class CashMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'direction' => 'out',
            'amount' => fake()->randomFloat(2, 50, 1000),
            'reason' => fake()->randomElement(['Petty cash for supplies', 'Change fund top-up', 'Bank deposit']),
            'actor_id' => User::factory(),
        ];
    }
}
