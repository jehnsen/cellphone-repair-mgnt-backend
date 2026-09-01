<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo dataset per docs/design/01-domain-design.md's Testing section,
 * layered on top of BaseInstallSeeder (the roles/branch/settings/users/
 * catalog baseline a real client install gets): serialized units,
 * customers, tickets spread across every status, 90 days of sales, a full
 * shift history, buy-backs, and installments.
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
            BaseInstallSeeder::class,
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
