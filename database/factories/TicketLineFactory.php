<?php

namespace Database\Factories;

use App\Models\RepairTicket;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\TicketLine> */
class TicketLineFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 300, 3000);

        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'line_type' => 'labor',
            'product_id' => null,
            'service_id' => Service::factory(),
            'stock_movement_id' => null,
            'description' => 'Labor charge',
            'quantity' => 1,
            'unit_cost' => null,
            'unit_price' => $unitPrice,
            'amount' => $unitPrice,
        ];
    }

    public function part(): static
    {
        return $this->state(fn () => [
            'line_type' => 'part',
            'service_id' => null,
            'product_id' => \App\Models\Product::factory()->part(),
            'description' => 'Replacement part',
        ]);
    }
}
