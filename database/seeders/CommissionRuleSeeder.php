<?php

namespace Database\Seeders;

use App\Models\CommissionRule;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommissionRuleSeeder extends Seeder
{
    public function run(): void
    {
        CommissionRule::factory()->create([
            'branch_id' => null,
            'technician_id' => null,
            'role' => 'technician',
            'basis' => 'percent_of_labor',
            'value' => 10,
            'effective_from' => now()->subMonths(6),
            'effective_to' => null,
        ]);

        // A richer per-technician override for one of the two seeded technicians.
        $technician = User::role('technician')->first();

        if ($technician) {
            CommissionRule::factory()->create([
                'branch_id' => $technician->branch_id,
                'technician_id' => $technician->id,
                'role' => null,
                'basis' => 'percent_of_margin',
                'value' => 15,
                'effective_from' => now()->subMonths(3),
                'effective_to' => null,
            ]);
        }
    }
}
