<?php

namespace Database\Factories;

use App\Models\DeviceBrand;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['handset', 'accessory', 'part']);
        $cost = fake()->randomFloat(2, 200, 15000);

        return [
            'sku' => strtoupper(fake()->unique()->bothify('???-####')),
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->words(3, true),
            'product_category_id' => ProductCategory::factory(),
            'device_brand_id' => $type === 'part' || fake()->boolean(70) ? DeviceBrand::factory() : null,
            'type' => $type,
            'cost' => $cost,
            'selling_price' => round($cost * fake()->randomFloat(2, 1.2, 1.8), 2),
            'is_serialized' => $type === 'handset',
            'reorder_point' => fake()->numberBetween(0, 10),
            'track_inventory' => true,
            'is_active' => true,
        ];
    }

    public function handset(): static
    {
        return $this->state(fn () => ['type' => 'handset', 'is_serialized' => true]);
    }

    public function part(): static
    {
        return $this->state(fn () => ['type' => 'part', 'is_serialized' => false]);
    }

    public function accessory(): static
    {
        return $this->state(fn () => ['type' => 'accessory', 'is_serialized' => false]);
    }
}
