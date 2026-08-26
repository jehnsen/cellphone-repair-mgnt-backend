<?php

namespace Database\Factories;

use App\Models\CommissionRule;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\CommissionEntry> */
class CommissionEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'technician_id' => User::factory(),
            'commission_rule_id' => CommissionRule::factory(),
            'amount' => fake()->randomFloat(2, 50, 500),
            'status' => 'pending',
            'reverses_entry_id' => null,
            'reversal_reason' => null,
            'created_at' => now(),
        ];
    }
}
