<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('serialized_units', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->char('imei', 15)->unique()->nullable();
            $table->string('serial_number', 60)->unique()->nullable();
            $table->enum('condition', ['brand_new', 'open_box', 'secondhand', 'refurbished']);
            $table->char('grade', 1)->nullable();
            $table->decimal('acquisition_cost', 14, 2)->default(0);
            $table->string('acquisition_source', 60)->nullable();
            $table->enum('status', ['in_stock', 'reserved', 'sold', 'for_repair', 'written_off'])->default('in_stock');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->text('warranty_terms')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Cached balance derived from stock_movements — never authoritative.
        Schema::create('stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('on_hand_qty', 14, 2)->default(0);
            $table->decimal('reserved_qty', 14, 2)->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['product_id', 'branch_id']);
        });

        // Append-only ledger — the source of truth for stock on hand.
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('serialized_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_cost', 14, 2);
            $table->enum('movement_type', [
                'receipt', 'sale', 'return_in', 'return_out', 'ticket_consumption',
                'adjustment', 'transfer_in', 'transfer_out', 'write_off',
            ]);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason_code', 40)->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->decimal('balance_after', 14, 2);
            $table->dateTime('occurred_at', 6);
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'branch_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['draft', 'submitted', 'partially_received', 'received', 'cancelled', 'closed'])->default('draft');
            $table->date('expected_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('ordered_qty', 14, 2);
            $table->decimal('received_qty', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2);
        });

        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('received_at');
            $table->timestamps();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_cost', 14, 2);
            $table->foreignId('serialized_unit_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('reason_code', 40);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('serialized_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity_delta', 14, 2);
            $table->decimal('unit_cost', 14, 2);
        });

        DB::statement('ALTER TABLE serialized_units ADD CONSTRAINT chk_serialized_units_identifier CHECK (imei IS NOT NULL OR serial_number IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('serialized_units');
        Schema::dropIfExists('suppliers');
    }
};
