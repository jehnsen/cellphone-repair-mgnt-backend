<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A unit shipped back to the supplier on a factory-defect claim is neither
 * `written_off` (that's a loss the shop eats) nor `sold` — it left our
 * hands but the supplier owes a replacement or a credit. Its own terminal
 * status keeps the supplier-return report and stock ledger honest.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE serialized_units MODIFY status ENUM(
            'in_stock', 'reserved', 'sold', 'for_repair', 'written_off', 'returned_to_supplier'
        ) NOT NULL DEFAULT 'in_stock'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE serialized_units MODIFY status ENUM(
            'in_stock', 'reserved', 'sold', 'for_repair', 'written_off'
        ) NOT NULL DEFAULT 'in_stock'");
    }
};
