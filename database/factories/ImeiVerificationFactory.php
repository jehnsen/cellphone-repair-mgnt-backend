<?php

namespace Database\Factories;

use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ImeiVerification> */
class ImeiVerificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'phase' => 'intake',
            'scanned_imei' => fake()->numerify('###############'),
            'matches_expected' => true,
            'actor_id' => User::factory(),
            'override_reason' => null,
            'overridden_by' => null,
            'created_at' => now(),
        ];
    }
}
