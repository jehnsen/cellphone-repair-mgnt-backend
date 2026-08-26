<?php

namespace Database\Factories;

use App\Models\RepairTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Warranty> */
class WarrantyFactory extends Factory
{
    public function definition(): array
    {
        $days = fake()->randomElement([7, 15, 30, 90]);
        $issuedAt = now();

        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'scope' => 'Covers parts and workmanship for this repair only.',
            'days' => $days,
            'issued_at' => $issuedAt,
            'expiry_date' => $issuedAt->clone()->addDays($days),
            'exclusions' => 'Does not cover physical or liquid damage after release.',
            'warranty_code' => 'WR-'.fake()->unique()->numerify('######'),
        ];
    }
}
