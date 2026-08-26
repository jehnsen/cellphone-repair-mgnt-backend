<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ProductCategory> */
class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Handsets', 'Screens', 'Batteries', 'Charging Ports', 'Cameras',
                'Speakers & Mics', 'Cases & Covers', 'Chargers & Cables', 'Earphones', 'Screen Protectors',
            ]),
            'parent_id' => null,
            'is_active' => true,
        ];
    }
}
