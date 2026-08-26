<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_tickets', function (Blueprint $table): void {
            $table->id();
            // This ulid also *is* the QR/claim-code payload — routes bind on it.
            $table->ulid('ulid')->unique();
            $table->string('ticket_number', 20)->unique();
            $table->string('claim_code', 10)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_device_id')->constrained()->restrictOnDelete();

            // Snapshotted at intake on purpose — later catalog edits never
            // rewrite ticket history.
            $table->string('device_brand_snapshot', 80)->nullable();
            $table->string('device_model_snapshot', 120)->nullable();
            $table->string('device_color_snapshot', 40)->nullable();

            $table->text('reported_problem')->nullable();
            $table->json('problem_tags')->nullable();
            $table->text('unlock_method')->nullable();
            $table->text('unlock_value')->nullable();
            $table->json('accessories_turned_over')->nullable();
            $table->json('intake_condition_checklist')->nullable();

            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->decimal('downpayment', 14, 2)->default(0);
            // Cached, service-maintained — depends on the payments table, so
            // it cannot be a true SQL generated column. See Flag 10.
            $table->decimal('balance', 14, 2)->default(0);

            $table->date('promised_date')->nullable();
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('status', [
                'received', 'diagnosed', 'awaiting_approval', 'awaiting_parts', 'in_repair',
                'qc', 'ready_for_pickup', 'released', 'unrepairable', 'returned_as_is', 'unclaimed',
            ])->default('received');
            $table->smallInteger('warranty_days_offered')->default(0);
            $table->boolean('terms_accepted')->default(false);
            $table->dateTime('terms_accepted_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'promised_date']);
            $table->index('customer_id');
            $table->index('customer_device_id');
        });

        Schema::create('ticket_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('line_type', ['part', 'labor']);
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 160)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        // Append-only timeline — every mutation anywhere in the ticket
        // lifecycle writes one of these.
        Schema::create('ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['repair_ticket_id', 'created_at']);
        });

        Schema::create('ticket_photos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('repair_ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('phase', ['intake', 'pre_repair', 'post_repair', 'release']);
            $table->string('storage_disk', 20)->default('local');
            $table->string('storage_path');
            $table->char('sha256_hash', 64);
            $table->dateTime('captured_at');
            $table->foreignId('captured_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ticket_quotes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('repair_ticket_id')->constrained()->cascadeOnDelete();
            $table->decimal('quoted_amount', 14, 2);
            $table->dateTime('sent_at');
            $table->enum('channel', ['call', 'sms', 'viber', 'email', 'in_person', 'app']);
            $table->dateTime('responded_at')->nullable();
            $table->enum('decision', ['approved', 'declined', 'partial', 'no_response'])->nullable();
            $table->text('responder_note')->nullable();
            $table->timestamps();
        });

        Schema::create('warranties', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('repair_ticket_id')->constrained()->restrictOnDelete();
            $table->text('scope')->nullable();
            $table->smallInteger('days');
            $table->dateTime('issued_at');
            $table->date('expiry_date');
            $table->text('exclusions')->nullable();
            $table->string('warranty_code', 20)->unique();
            $table->timestamps();
        });

        Schema::create('warranty_claims', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('warranty_id')->constrained()->restrictOnDelete();
            $table->foreignId('child_ticket_id')->constrained('repair_tickets')->restrictOnDelete();
            $table->enum('fault_attribution', ['part_defect', 'workmanship', 'customer_damage', 'not_covered']);
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE ticket_lines ADD CONSTRAINT chk_ticket_lines_reference CHECK ('
            .'(line_type = \'part\' AND product_id IS NOT NULL AND service_id IS NULL) OR '
            .'(line_type = \'labor\' AND service_id IS NOT NULL AND product_id IS NULL)'
            .')'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('ticket_quotes');
        Schema::dropIfExists('ticket_photos');
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('ticket_lines');
        Schema::dropIfExists('repair_tickets');
    }
};
