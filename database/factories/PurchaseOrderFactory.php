<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'supplier_id' => Supplier::factory(),
            'status' => 'draft',
            'expected_date' => fake()->dateTimeBetween('now', '+2 weeks'),
            'created_by' => User::factory(),
        ];
    }
}
