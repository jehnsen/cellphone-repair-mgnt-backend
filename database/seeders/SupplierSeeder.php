<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        collect(['Greenhills Mobile Parts Trading', 'SM Electronics Wholesale', 'Cebu Gadget Distributors'])
            ->each(fn (string $name) => Supplier::factory()->create(['name' => $name]));
    }
}
