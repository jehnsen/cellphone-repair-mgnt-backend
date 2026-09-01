<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not every branch does repairs. A `sales_only` branch is a pure retail
 * counter (appliances, phones, laptops, accessories) — it runs POS,
 * inventory, and buy-backs, but the whole repair surface (job orders, the
 * status board, part swaps, refurb jobs) is closed there.
 *
 * Enforced in App\Policies\Concerns\ChecksBranchCapabilities, not by a DB
 * constraint: which endpoints a branch type may reach is an authorization
 * rule, not a row-shape rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->enum('type', ['repair_and_sales', 'sales_only'])
                ->default('repair_and_sales')
                ->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
