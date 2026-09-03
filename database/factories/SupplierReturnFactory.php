<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\SerializedUnit;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierReturn> */
class SupplierReturnFactory extends Factory
{
    public function definition(): array
    {
        $branch = Branch::factory()->create();
        $unit = SerializedUnit::factory()->for($branch, 'branch')->create(['status' => 'returned_to_supplier']);

        return [
            'branch_id' => $branch->id,
            'supplier_id' => Supplier::factory(),
            'serialized_unit_id' => $unit->id,
            'sale_warranty_claim_id' => null,
            'reason' => fake()->randomElement(['factory_defect', 'dead_on_arrival', 'wrong_item', 'other']),
            'reason_note' => null,
            'status' => 'sent',
            'replacement_serialized_unit_id' => null,
            'credit_amount' => null,
            'sent_at' => now()->toDateString(),
            'resolved_at' => null,
            'processed_by' => User::factory()->create(['branch_id' => $branch->id])->id,
        ];
    }
}
