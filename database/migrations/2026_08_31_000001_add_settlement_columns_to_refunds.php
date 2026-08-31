<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A refund now records *how* the money went back (`refund_method`) and the
 * total it settled (`total_amount`, the sum of its refund_lines). A `cash`
 * refund writes a `cash_movements` (direction=out) row against the
 * cashier's open shift so ShiftService::close() actually subtracts it from
 * expected_cash; `store_credit` issues into the customer's store-credit
 * ledger; the electronic methods are reversed out-of-band by the processor.
 * `trade_in` is intentionally not a refund method — you don't hand a phone
 * back as a refund.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->enum('refund_method', ['cash', 'gcash', 'maya', 'card', 'bank_transfer', 'store_credit'])
                ->default('cash')
                ->after('reason_code');
            $table->decimal('total_amount', 14, 2)->default(0)->after('refund_method');
        });

        DB::statement('ALTER TABLE refunds ADD CONSTRAINT chk_refunds_total_nonneg CHECK (total_amount >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE refunds DROP CONSTRAINT chk_refunds_total_nonneg');

        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropColumn(['refund_method', 'total_amount']);
        });
    }
};
