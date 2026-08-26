<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo dataset per docs/design/01-domain-design.md's Testing section: 2
 * branches, 8 users across all roles, 25 device models, 60 products across
 * all three types, 40 serialized units, 25 customers, 120 tickets spread
 * across every status, 90 days of sales, and a full shift history.
 *
 * Does NOT use WithoutModelEvents — model events are load-bearing here
 * (HasUlid generates ulids on creating(), VerificationToken generates its
 * own token the same way).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            BranchSeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
            SupplierSeeder::class,
            CustomerSeeder::class,
            InventorySeeder::class,
            RepairTicketSeeder::class,
            ShiftAndSalesSeeder::class,
            BuybackSeeder::class,
            InstallmentSeeder::class,
            MessageTemplateSeeder::class,
            CommissionRuleSeeder::class,
        ]);
    }
}
