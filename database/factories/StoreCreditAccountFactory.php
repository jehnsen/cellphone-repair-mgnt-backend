<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\StoreCreditAccount> */
class StoreCreditAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'balance' => 0,
        ];
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn () => ['balance' => $balance]);
    }
}
