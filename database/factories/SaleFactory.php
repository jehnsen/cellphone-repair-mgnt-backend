<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Sale> */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 15000);
        $vat = round($subtotal / 1.12 * 0.12, 2);

        return [
            'branch_id' => Branch::factory(),
            'sale_number' => 'SI-'.fake()->unique()->numerify('202508-#####'),
            'customer_id' => null,
            'cashier_id' => User::factory(),
            'shift_id' => Shift::factory(),
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'vat_amount' => $vat,
            'vatable_sales' => $subtotal - $vat,
            'vat_exempt_sales' => 0,
            'zero_rated_sales' => 0,
            'total' => $subtotal,
            'status' => 'completed',
            'void_reason' => null,
            'source' => 'pos',
            'client_uuid' => fake()->unique()->uuid(),
        ];
    }
}
