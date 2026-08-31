<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store credit is shop-wide: one account per customer, redeemable at any
 * branch (a 2-branch shop shouldn't strand a customer's credit at the
 * counter that issued it), so neither table carries `branch_id` — the
 * customer already does. `store_credit_entries` is an append-only ledger
 * (const UPDATED_AT = null) with `balance_after` stamped on every row;
 * `store_credit_accounts.balance` is the cached, service-maintained
 * running total, same shape as `stock_levels` — never authoritative on its
 * own, always written inside the same transaction as the entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_credit_accounts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE store_credit_accounts ADD CONSTRAINT chk_sca_balance_nonneg CHECK (balance >= 0)');

        Schema::create('store_credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_credit_account_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('reason', 60);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['store_credit_account_id', 'created_at']);
        });

        DB::statement('ALTER TABLE store_credit_entries ADD CONSTRAINT chk_sce_amount_pos CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('store_credit_entries');
        Schema::dropIfExists('store_credit_accounts');
    }
};
