<?php

namespace Database\Factories;

use App\Models\RepairTicket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\VerificationToken> */
class VerificationTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'token' => Str::random(32),
            'revoked_at' => null,
        ];
    }
}
