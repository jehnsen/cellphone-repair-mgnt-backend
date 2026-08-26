<?php

namespace Database\Factories;

use App\Support\PhilippineFaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Branch> */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        $city = fake()->randomElement(['Quezon City', 'Makati', 'Cebu City', 'Davao City', 'Pasig', 'Taguig']);

        return [
            'name' => 'FixMo Phone Repair — '.$city,
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'legal_name' => 'FixMo Phone Repair Services Corp.',
            'address_line1' => fake()->buildingNumber().' '.fake()->streetName(),
            'address_line2' => null,
            'city' => $city,
            'province' => 'Metro Manila',
            'postal_code' => fake()->numerify('####'),
            'contact_phone' => PhilippineFaker::mobile(),
            'contact_email' => fake()->companyEmail(),
            'tin' => PhilippineFaker::tin(),
            'bir_permit_no' => fake()->numerify('BIR-#########'),
            'vat_registered' => true,
            'receipt_header_text' => 'FixMo Phone Repair Services',
            'receipt_footer_text' => 'Thank you for choosing FixMo!',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ];
    }
}
