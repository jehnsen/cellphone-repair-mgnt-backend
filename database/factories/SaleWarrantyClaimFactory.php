<?php

namespace Database\Factories;

use App\Models\SaleWarranty;
use App\Models\SaleWarrantyClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleWarrantyClaim> */
class SaleWarrantyClaimFactory extends Factory
{
    public function definition(): array
    {
        $warranty = SaleWarranty::factory()->create();

        return [
            'branch_id' => $warranty->branch_id,
            'sale_warranty_id' => $warranty->id,
            'serialized_unit_id' => $warranty->serialized_unit_id,
            'reported_defect' => fake()->sentence(),
            'handling' => 'separate',
            'repair_ticket_id' => null,
            'within_coverage' => true,
            'status' => 'open',
            'resolution' => null,
            'outcome_notes' => null,
            'filed_by' => User::factory()->create(['branch_id' => $warranty->branch_id])->id,
            'resolved_by' => null,
            'resolved_at' => null,
        ];
    }

    public function resolved(string $resolution = 'repaired_in_house'): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => $resolution === 'rejected' ? 'rejected' : 'resolved',
            'resolution' => $resolution,
            'resolved_by' => User::factory()->create(['branch_id' => $attrs['branch_id']])->id,
            'resolved_at' => now(),
        ]);
    }
}
