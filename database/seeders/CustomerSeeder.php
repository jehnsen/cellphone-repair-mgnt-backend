<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\DeviceModel;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();
        $deviceModels = DeviceModel::all();

        Customer::factory()
            ->count(23)
            ->sequence(fn ($sequence) => ['branch_id' => $branches[$sequence->index % $branches->count()]->id])
            ->create()
            ->each(function (Customer $customer) use ($deviceModels): void {
                CustomerDevice::factory()
                    ->count(random_int(1, 2))
                    ->create([
                        'customer_id' => $customer->id,
                        'device_model_id' => $deviceModels->random()->id,
                    ]);
            });

        // 2 of the 25 are blacklisted, for the intake flow to check against.
        Customer::factory()
            ->count(2)
            ->blacklisted()
            ->sequence(fn ($sequence) => ['branch_id' => $branches[$sequence->index % $branches->count()]->id])
            ->create();
    }
}
