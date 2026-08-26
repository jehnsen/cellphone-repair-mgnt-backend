<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\RepairTicket> */
class RepairTicketFactory extends Factory
{
    public function definition(): array
    {
        $receivedAt = fake()->dateTimeBetween('-90 days', 'now');
        $estimatedCost = fake()->randomFloat(2, 300, 8000);

        return [
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'customer_device_id' => CustomerDevice::factory(),
            'ticket_number' => 'JO-'.fake()->unique()->numerify('202508-####'),
            'claim_code' => strtoupper(fake()->unique()->bothify('??####')),
            'device_brand_snapshot' => fake()->randomElement(['Samsung', 'Apple', 'Xiaomi', 'Oppo', 'Vivo', 'Realme', 'Infinix']),
            'device_model_snapshot' => fake()->word().' '.fake()->numberBetween(5, 15),
            'device_color_snapshot' => fake()->safeColorName(),
            'reported_problem' => fake()->randomElement([
                'Cracked screen, touch unresponsive in top corner.',
                'Battery drains within 2 hours.',
                'Not charging, no response from charging port.',
                'Water damage after being dropped in sink.',
                'Speaker crackling at high volume.',
                'Camera app force closes on launch.',
            ]),
            'problem_tags' => fake()->randomElements(['screen', 'battery', 'charging', 'water_damage', 'audio', 'camera'], fake()->numberBetween(1, 2)),
            'unlock_method' => fake()->randomElement(['pin', 'pattern', 'none']),
            'unlock_value' => fake()->optional(0.7)->numerify('####'),
            'accessories_turned_over' => fake()->randomElements(['charger', 'case', 'sim_tray', 'earphones'], fake()->numberBetween(0, 2)),
            'intake_condition_checklist' => ['screen_condition' => 'scratched', 'body_condition' => 'good'],
            'estimated_cost' => $estimatedCost,
            'approved_amount' => null,
            'downpayment' => 0,
            'balance' => 0,
            'promised_date' => fake()->dateTimeBetween($receivedAt, '+7 days'),
            'assigned_technician_id' => null,
            'status' => 'received',
            'warranty_days_offered' => 0,
            'terms_accepted' => true,
            'terms_accepted_at' => $receivedAt,
            'created_at' => $receivedAt,
            'updated_at' => $receivedAt,
        ];
    }
}
