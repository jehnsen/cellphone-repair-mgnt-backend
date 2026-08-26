<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Screen Replacement', 'Battery Replacement', 'Charging Port Repair',
                'Water Damage Cleaning', 'Speaker Repair', 'Camera Replacement',
                'Diagnostic Checkup', 'Software Flash / Unbrick', 'Back Glass Replacement',
            ]),
            'category' => fake()->randomElement(['screen', 'battery', 'connector', 'water_damage', 'audio', 'camera', 'diagnostics', 'software']),
            'default_price' => fake()->randomFloat(2, 300, 4500),
            'default_duration_minutes' => fake()->randomElement([30, 45, 60, 90, 120, 180]),
            'warranty_days' => fake()->randomElement([0, 7, 15, 30, 90]),
            'is_active' => true,
        ];
    }
}
