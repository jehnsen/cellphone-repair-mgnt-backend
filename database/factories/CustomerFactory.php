<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Support\PhilippineFaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => PhilippineFaker::fullName(),
            'mobile' => PhilippineFaker::mobile(),
            'email' => fake()->optional(0.6)->safeEmail(),
            'address' => fake()->optional(0.7)->address(),
            'notes' => null,
            'is_blacklisted' => false,
            'blacklist_reason' => null,
        ];
    }

    public function blacklisted(): static
    {
        return $this->state(fn () => [
            'is_blacklisted' => true,
            'blacklist_reason' => fake()->randomElement([
                'Repeatedly failed to claim repaired devices.',
                'Bounced payment on a prior repair.',
                'Abusive behavior toward staff.',
            ]),
        ]);
    }
}
