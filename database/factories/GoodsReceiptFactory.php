<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\GoodsReceipt> */
class GoodsReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'purchase_order_id' => null,
            'supplier_id' => Supplier::factory(),
            'status' => 'posted',
            'received_by' => User::factory(),
            'received_at' => now(),
        ];
    }
}
