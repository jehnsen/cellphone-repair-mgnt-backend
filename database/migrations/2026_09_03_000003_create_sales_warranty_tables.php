<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales-side warranty, kept entirely separate from the repair-ticket
 * `warranties` / `warranty_claims` pair. A serialized unit sold at POS
 * carries its own shop (or manufacturer) warranty; a customer availing it
 * files a `sale_warranty_claim` that lives under CP units, never spawning
 * a job order (though it may optionally reference one). A unit sent back
 * to the vendor for a factory defect is a `supplier_return`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_warranties', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            // sale_lines has no ulid of its own — nested/internal only — so
            // the FK is by internal id, same as ticket_lines.stock_movement_id.
            $table->foreignId('sale_line_id')->constrained('sale_lines')->restrictOnDelete();
            $table->foreignId('serialized_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('coverage', ['shop', 'manufacturer'])->default('shop');
            $table->unsignedSmallInteger('term_days');
            $table->date('starts_at');
            $table->date('expiry_date');
            $table->string('warranty_code', 20)->unique();
            $table->text('terms')->nullable();
            $table->text('exclusions')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->timestamps();

            // One warranty per sold line; re-selling a returned unit is a
            // new line and a new warranty.
            $table->unique('sale_line_id');
            $table->index(['serialized_unit_id', 'expiry_date']);
        });

        Schema::create('sale_warranty_claims', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_warranty_id')->constrained()->restrictOnDelete();
            // Denormalized off the warranty so the unit's claim history is a
            // single-column lookup.
            $table->foreignId('serialized_unit_id')->constrained()->restrictOnDelete();
            $table->text('reported_defect');
            // 'separate' keeps the claim purely under CP units; 'repair_board'
            // additionally pins an existing job order for the bench work.
            $table->enum('handling', ['separate', 'repair_board'])->default('separate');
            $table->foreignId('repair_ticket_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('within_coverage');
            $table->enum('status', ['open', 'resolved', 'rejected'])->default('open');
            $table->enum('resolution', [
                'repaired_in_house', 'replaced', 'returned_to_supplier', 'refunded', 'rejected',
            ])->nullable();
            $table->text('outcome_notes')->nullable();
            $table->foreignId('filed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('supplier_returns', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('serialized_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_warranty_claim_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('reason', ['factory_defect', 'dead_on_arrival', 'wrong_item', 'other']);
            $table->text('reason_note')->nullable();
            $table->enum('status', ['sent', 'replaced', 'credited', 'rejected', 'closed'])->default('sent');
            $table->foreignId('replacement_serialized_unit_id')->nullable()->constrained('serialized_units')->restrictOnDelete();
            $table->decimal('credit_amount', 14, 2)->nullable();
            $table->date('sent_at');
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_returns');
        Schema::dropIfExists('sale_warranty_claims');
        Schema::dropIfExists('sale_warranties');
    }
};
