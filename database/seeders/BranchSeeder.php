<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::factory()->create([
            'name' => 'FixMo Phone Repair — Quezon City (Main)',
            'code' => 'QC',
            'city' => 'Quezon City',
        ]);

        Branch::factory()->create([
            'name' => 'FixMo Phone Repair — Cebu City',
            'code' => 'CEB',
            'city' => 'Cebu City',
            'province' => 'Cebu',
        ]);
    }
}
