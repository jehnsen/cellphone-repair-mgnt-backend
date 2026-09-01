<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::factory()->create([
            'name' => 'Nelson Cellphone and Computer Repair',
            'code' => 'AL',
            'city' => 'Alfonso Lista',
        ]);
    }
}
