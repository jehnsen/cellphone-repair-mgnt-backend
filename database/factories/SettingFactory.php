<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Setting> */
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

    /** A shop-wide default row (branch_id = null). */
    public function global(): static
    {
        return $this->state(fn () => ['branch_id' => null]);
    }

    /** A concrete key/value/type triple. */
    public function pair(string $key, mixed $value, string $type = 'string'): static
    {
        return $this->state(fn () => ['key' => $key, 'value' => $value, 'type' => $type]);
    }
}
