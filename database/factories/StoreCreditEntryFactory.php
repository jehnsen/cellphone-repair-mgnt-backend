<?php

namespace Database\Factories;

use App\Models\StoreCreditAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\StoreCreditEntry> */
class StoreCreditEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_credit_account_id' => StoreCreditAccount::factory(),
            'direction' => 'credit',
            'amount' => 100,
            'balance_after' => 100,
            'reason' => 'refund',
            'reference_type' => null,
            'reference_id' => null,
            'actor_id' => User::factory(),
        ];
    }
}
