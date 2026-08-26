<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\DeviceBrand> */
class DeviceBrandFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'logo_ref' => null,
            'is_active' => true,
        ];
    }
}
