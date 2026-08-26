<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\DeviceModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\CustomerDevice> */
class CustomerDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'device_model_id' => DeviceModel::factory(),
            'imei_normalized' => fake()->numerify('###############'),
            'serial_number' => fake()->optional(0.3)->bothify('SN########'),
            'color' => fake()->randomElement(['Black', 'White', 'Blue', 'Green', 'Gold', 'Silver', 'Midnight', 'Starlight']),
            'notes' => null,
        ];
    }
}
