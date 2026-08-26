<?php

namespace Database\Factories;

use App\Models\RepairTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\TicketQuote> */
class TicketQuoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'quoted_amount' => fake()->randomFloat(2, 300, 8000),
            'sent_at' => now(),
            'channel' => fake()->randomElement(['call', 'sms', 'viber', 'in_person']),
            'responded_at' => null,
            'decision' => null,
            'responder_note' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['responded_at' => now(), 'decision' => 'approved']);
    }
}
