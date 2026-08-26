<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 15000);

        return [
            'payable_type' => 'sale',
            'payable_id' => Sale::factory(),
            'method' => 'cash',
            'amount' => $amount,
            'reference_number' => null,
            'tendered' => $amount,
            'change_given' => 0,
            'shift_id' => null,
            'actor_id' => User::factory(),
        ];
    }

    public function gcash(): static
    {
        return $this->state(fn () => [
            'method' => 'gcash',
            'reference_number' => fake()->numerify('GC#########'),
            'tendered' => null,
            'change_given' => null,
        ]);
    }
}
