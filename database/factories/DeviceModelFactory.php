<?php

namespace Database\Factories;

use App\Models\DeviceBrand;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\DeviceModel> */
class DeviceModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_brand_id' => DeviceBrand::factory(),
            'name' => fake()->word().' '.fake()->numberBetween(1, 20),
            'release_year' => fake()->numberBetween(2018, 2026),
            'aliases' => [],
            'is_active' => true,
        ];
    }
}
