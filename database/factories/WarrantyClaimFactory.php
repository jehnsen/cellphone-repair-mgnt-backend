<?php

namespace Database\Factories;

use App\Models\RepairTicket;
use App\Models\Warranty;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\WarrantyClaim> */
class WarrantyClaimFactory extends Factory
{
    public function definition(): array
    {
        return [
            'warranty_id' => Warranty::factory(),
            'child_ticket_id' => RepairTicket::factory(),
            'fault_attribution' => fake()->randomElement(['part_defect', 'workmanship', 'customer_damage', 'not_covered']),
            'product_id' => null,
        ];
    }
}
