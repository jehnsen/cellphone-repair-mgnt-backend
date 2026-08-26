<?php

namespace Database\Factories;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Discount> */
class DiscountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'sale_line_id' => null,
            'type' => 'percent',
            'value' => 10,
            'scope' => 'sale',
            'id_type' => null,
            'id_number' => null,
            'cardholder_name' => null,
            'signature_ref' => null,
        ];
    }

    public function seniorCitizen(): static
    {
        return $this->state(fn () => [
            'type' => 'senior_citizen',
            'value' => 20,
            'id_type' => 'OSCA ID',
            'id_number' => fake()->numerify('OSCA-#######'),
            'cardholder_name' => fake()->name(),
        ]);
    }
}
