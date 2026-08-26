<?php

namespace Database\Factories;

use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\TicketEvent> */
class TicketEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'actor_id' => User::factory(),
            'event_type' => 'status_changed',
            'from_status' => 'received',
            'to_status' => 'diagnosed',
            'note' => null,
            'metadata' => [],
            'created_at' => now(),
        ];
    }
}
