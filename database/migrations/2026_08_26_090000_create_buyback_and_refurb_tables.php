<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acquisitions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('seller_name', 120);
            $table->string('seller_id_type', 30);
            $table->string('seller_id_number', 60);
            $table->string('seller_id_photo_ref');
            $table->text('declared_source')->nullable();
            $table->decimal('offered_price', 14, 2);
            $table->char('imei', 15);
            $table->text('condition_assessment')->nullable();
            $table->date('purchase_date');
            // A required gate, not a field: an acquisition cannot be
            // completed while this is 'flagged' — enforced in the service
            // layer (legal exposure for the shop, see design doc §2.8).
            $table->enum('imei_check_result', ['clear', 'flagged', 'not_checked'])->default('not_checked');
            $table->dateTime('imei_checked_at')->nullable();
            $table->foreignId('resulting_serialized_unit_id')->nullable()->constrained('serialized_units')->restrictOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('refurb_jobs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('acquisition_id')->constrained()->restrictOnDelete();
            $table->foreignId('serialized_unit_id')->constrained()->restrictOnDelete();
            $table->decimal('labor_cost', 14, 2)->default(0);
            $table->decimal('parts_cost', 14, 2)->default(0);
            $table->decimal('landed_cost', 14, 2)->default(0);
            $table->enum('status', ['open', 'completed'])->default('open');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refurb_job_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refurb_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_cost', 14, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refurb_job_lines');
        Schema::dropIfExists('refurb_jobs');
        Schema::dropIfExists('acquisitions');
    }
};
