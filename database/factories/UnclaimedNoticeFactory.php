<?php

namespace Database\Factories;

use App\Models\RepairTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\UnclaimedNotice> */
class UnclaimedNoticeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'stage' => 30,
            'generated_at' => now(),
            'delivered_at' => now(),
            'method' => 'sms',
            'notice_payload' => ['message' => 'Your device has been ready for pickup for 30 days.'],
        ];
    }
}
