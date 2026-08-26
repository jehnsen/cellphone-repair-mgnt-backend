<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->enum('channel', ['viber', 'sms', 'email']);
            $table->string('event_key', 60);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['channel', 'event_key']);
        });

        // Append-only. Dispatch is a queued job behind a NotificationChannel
        // interface with a LogOnlyDriver default until a provider contract
        // exists (see docs/design/01-domain-design.md §2.10).
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('recipient', 120);
            $table->enum('channel', ['viber', 'sms', 'email']);
            $table->foreignId('message_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('rendered_body')->nullable();
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued');
            $table->string('provider_reference', 120)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('unclaimed_notices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('repair_ticket_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('stage');
            $table->dateTime('generated_at');
            $table->dateTime('delivered_at')->nullable();
            $table->enum('method', ['sms', 'viber', 'email', 'call', 'mail'])->nullable();
            $table->json('notice_payload')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Locked counter table producing gapless per-branch, per-year
        // document number series — SELECT ... FOR UPDATE inside the
        // transaction, never MAX(id)+1.
        Schema::create('sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('scope', 30);
            $table->smallInteger('year');
            $table->tinyInteger('month')->nullable();
            $table->unsignedInteger('last_number')->default(0);

            $table->unique(['branch_id', 'scope', 'year', 'month']);
        });

        // Append-only. printable is polymorphic (ticket | sale | warranty |
        // unclaimed_notice | shift), left unconstrained by design — same
        // reasoning as the other polymorphic columns in this schema.
        Schema::create('document_prints', function (Blueprint $table): void {
            $table->id();
            $table->enum('document_type', [
                'claim_stub', 'acknowledgment_receipt', 'warranty_slip',
                'job_order', 'unclaimed_notice', 'shift_report',
            ]);
            $table->string('printable_type', 40);
            $table->unsignedBigInteger('printable_id');
            $table->enum('kind', ['original', 'reprint']);
            $table->unsignedInteger('sequence_no');
            $table->foreignId('printed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('printed_at');

            $table->index(['printable_type', 'printable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_prints');
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('unclaimed_notices');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('message_templates');
    }
};
