<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Support\PhilippineFaker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        $name = PhilippineFaker::fullName();

        return [
            'branch_id' => Branch::factory(),
            'employee_code' => 'EMP-'.fake()->unique()->numerify('####'),
            'name' => $name,
            'email' => Str::slug($name).'.'.fake()->unique()->numerify('###').'@fixmo.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'last_login_at' => fake()->optional(0.7)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
