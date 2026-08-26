<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PartSwap> */
class PartSwapFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'removed_description' => 'Original OEM screen assembly',
            'removed_serial' => null,
            'removed_photo_ref' => null,
            'installed_product_id' => Product::factory()->part(),
            'installed_serial' => null,
            'disposition' => fake()->randomElement(['returned_to_customer', 'retained_for_disposal', 'returned_to_supplier']),
            'technician_id' => User::factory(),
            'created_at' => now(),
        ];
    }
}
