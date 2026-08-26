<?php

namespace Database\Factories;

use App\Support\PhilippineFaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Supplier> */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' '.fake()->randomElement(['Trading', 'Parts Supply', 'Distribution Inc.', 'Electronics']),
            'contact_name' => PhilippineFaker::fullName(),
            'contact_phone' => PhilippineFaker::mobile(),
            'contact_email' => fake()->companyEmail(),
            'terms' => fake()->randomElement(['COD', 'Net 15', 'Net 30', '50% downpayment']),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
