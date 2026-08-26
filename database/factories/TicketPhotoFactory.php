<?php

namespace Database\Factories;

use App\Models\RepairTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\TicketPhoto> */
class TicketPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'phase' => 'intake',
            'storage_disk' => 'local',
            'storage_path' => 'ticket-photos/'.fake()->uuid().'.jpg',
            'sha256_hash' => hash('sha256', fake()->uuid()),
            'captured_at' => now(),
            'captured_by' => null,
        ];
    }
}
