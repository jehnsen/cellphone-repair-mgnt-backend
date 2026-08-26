<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('opened_at');
            $table->decimal('opening_float', 14, 2)->default(0);
            $table->dateTime('closed_at')->nullable();
            $table->decimal('counted_cash', 14, 2)->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('variance', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('sale_number', 20)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('vatable_sales', 14, 2)->default(0);
            $table->decimal('vat_exempt_sales', 14, 2)->default(0);
            $table->decimal('zero_rated_sales', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->enum('status', ['completed', 'voided', 'refunded', 'partially_refunded'])->default('completed');
            $table->text('void_reason')->nullable();
            $table->enum('source', ['pos', 'online', 'offline_sync'])->default('pos');
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
        });

        // Polymorphic sellable (product | serialized_unit | service |
        // ticket_balance) — no single-column FK is possible across three
        // different target tables, so sellable_id is left unconstrained by
        // design.
        Schema::create('sale_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->enum('sellable_type', ['product', 'serialized_unit', 'service', 'ticket_balance']);
            $table->unsignedBigInteger('sellable_id')->nullable();
            $table->decimal('quantity', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('line_discount', 14, 2)->default(0);
            $table->decimal('amount', 14, 2);

            $table->index(['sellable_type', 'sellable_id']);
        });

        Schema::create('discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_line_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', ['percent', 'amount', 'senior_citizen', 'pwd']);
            $table->decimal('value', 14, 2);
            $table->enum('scope', ['line', 'sale']);
            $table->string('id_type', 30)->nullable();
            $table->string('id_number', 40)->nullable();
            $table->string('cardholder_name', 120)->nullable();
            $table->string('signature_ref')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Polymorphic payable (sale | repair_ticket) — same reasoning as
        // sale_lines.sellable_id above, left unconstrained by design.
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->enum('payable_type', ['sale', 'repair_ticket']);
            $table->unsignedBigInteger('payable_id');
            $table->enum('method', ['cash', 'gcash', 'maya', 'card', 'bank_transfer', 'store_credit', 'trade_in']);
            $table->decimal('amount', 14, 2);
            $table->string('reference_number', 60)->nullable();
            $table->decimal('tendered', 14, 2)->nullable();
            $table->decimal('change_given', 14, 2)->nullable();
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['payable_type', 'payable_id']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->string('reason_code', 40);
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('refund_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_line_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->enum('restock_behavior', ['restock', 'no_restock', 'write_off']);
        });

        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->decimal('amount', 14, 2);
            $table->string('reason', 160);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('refund_lines');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('shifts');
    }
};
