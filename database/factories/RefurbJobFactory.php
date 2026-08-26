<?php

namespace Database\Factories;

use App\Models\Acquisition;
use App\Models\SerializedUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\RefurbJob> */
class RefurbJobFactory extends Factory
{
    public function definition(): array
    {
        $labor = fake()->randomFloat(2, 200, 1500);
        $parts = fake()->randomFloat(2, 0, 3000);

        return [
            'acquisition_id' => Acquisition::factory(),
            'serialized_unit_id' => SerializedUnit::factory(),
            'labor_cost' => $labor,
            'parts_cost' => $parts,
            'landed_cost' => $labor + $parts,
            'status' => 'open',
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'completed_at' => now()]);
    }
}
