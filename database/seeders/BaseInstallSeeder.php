<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The baseline every fresh client install needs and nothing more: the
 * role/permission matrix, the shop's branches (the main repair branch plus
 * the sales-only retail branch), shop-wide settings, the staff accounts,
 * and the full product/service catalog (device brands and models, handset
 * SKUs, accessories, parts, repair services).
 *
 * Deliberately excludes every demo/transactional seeder that
 * DatabaseSeeder pulls in (customers, inventory units, tickets, sales,
 * buy-backs, installments, ...) — a real shop starts those empty.
 *
 * This is what POST /api/v1/system/fresh-install re-runs after wiping the
 * database, and what DatabaseSeeder builds its demo dataset on top of.
 *
 * Does NOT use WithoutModelEvents — model events are load-bearing
 * (HasUlid generates ulids on creating()).
 */
class BaseInstallSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            BranchSeeder::class,
            SettingSeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
