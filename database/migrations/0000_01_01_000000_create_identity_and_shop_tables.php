<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            // Short code embedded in document numbers (JO-{code}-YYYYMM-####)
            // so per-branch sequences (see `sequences` table) still produce
            // globally unique ticket/sale numbers across branches.
            $table->string('code', 10)->unique();
            $table->string('legal_name')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->char('tin', 15)->nullable();
            $table->string('bir_permit_no')->nullable();
            $table->boolean('vat_registered')->default(true);
            $table->text('receipt_header_text')->nullable();
            $table->text('receipt_footer_text')->nullable();
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Branch-scoped (branch_id nullable = global default), key/value,
        // typed casts, cached per branch. See docs/design/01-domain-design.md §2.1.
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->enum('type', ['string', 'int', 'decimal', 'bool', 'json'])->default('string');
            $table->timestamps();

            $table->unique(['branch_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('branches');
    }
};
