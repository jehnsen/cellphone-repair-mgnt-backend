<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Setting> */
class SettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'key' => fake()->unique()->word(),
            'value' => ['enabled' => true],
            'type' => 'json',
        ];
    }
}
