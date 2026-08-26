<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\SerializedUnit> */
class SerializedUnitFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 3000, 60000);

        return [
            'product_id' => Product::factory()->handset(),
            'imei' => fake()->unique()->numerify('###############'),
            'serial_number' => null,
            'condition' => fake()->randomElement(['brand_new', 'open_box', 'secondhand', 'refurbished']),
            'grade' => fake()->optional(0.4)->randomElement(['A', 'B', 'C']),
            'acquisition_cost' => $cost,
            'acquisition_source' => fake()->randomElement(['supplier', 'trade_in', 'buyback']),
            'status' => 'in_stock',
            'branch_id' => Branch::factory(),
            'warranty_terms' => fake()->optional(0.5)->sentence(),
        ];
    }

    public function sold(): static
    {
        return $this->state(fn () => ['status' => 'sold']);
    }

    public function forRepair(): static
    {
        return $this->state(fn () => ['status' => 'for_repair']);
    }
}
