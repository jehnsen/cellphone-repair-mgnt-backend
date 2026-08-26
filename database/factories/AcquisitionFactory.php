<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use App\Support\PhilippineFaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Acquisition> */
class AcquisitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'seller_name' => PhilippineFaker::fullName(),
            'seller_id_type' => fake()->randomElement(["Driver's License", 'UMID', 'Passport', "Voter's ID"]),
            'seller_id_number' => fake()->bothify('??-####-#######'),
            'seller_id_photo_ref' => 'seller-ids/'.fake()->uuid().'.jpg',
            'declared_source' => 'Personal use, upgrading to a new phone.',
            'offered_price' => fake()->randomFloat(2, 1500, 25000),
            'imei' => fake()->unique()->numerify('###############'),
            'condition_assessment' => fake()->randomElement(['Good condition, minor scratches', 'Cracked back glass', 'Battery health at 78%']),
            'purchase_date' => fake()->dateTimeBetween('-60 days', 'now'),
            'imei_check_result' => 'clear',
            'imei_checked_at' => now(),
            'resulting_serialized_unit_id' => null,
            'processed_by' => User::factory(),
        ];
    }

    public function flagged(): static
    {
        return $this->state(fn () => ['imei_check_result' => 'flagged']);
    }
}
