<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A `method = trade_in` payment points at the completed buy-back
 * `Acquisition` whose appraised `offered_price` is the credit being
 * applied. The FK is unique-by-usage (enforced in PaymentRecorder, not the
 * schema — a partial unique index on a nullable column is awkward across
 * engines): a given acquisition can back at most one trade-in payment.
 * trade_in payments never touch `expected_cash` (ShiftService only counts
 * `method = cash`), so there is no drawer-reconciliation impact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('acquisition_id')->nullable()->after('shift_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('acquisition_id');
        });
    }
};
