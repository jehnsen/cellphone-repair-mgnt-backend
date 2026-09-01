<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Support\BranchType;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        // Main branch — repair bench plus retail counter.
        Branch::factory()->create([
            'name' => 'Nelson Cellphone and Computer Repair',
            'code' => 'AL',
            'city' => 'Alfonso Lista',
            'type' => BranchType::RepairAndSales,
        ]);

        // Second branch — pure retail (appliances, cellphones, laptops,
        // accessories). No repair bench, so the whole job-order surface is
        // closed there; see ChecksBranchCapabilities.
        Branch::factory()->create([
            'name' => 'Nelson Sales Center',
            'code' => 'SC',
            'city' => 'Alfonso Lista',
            'type' => BranchType::SalesOnly,
        ]);
    }
}
