<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One findings record per ticket — the record of what was actually wrong
 * with a unit and what was done about it, which until now existed only as
 * un-reportable free-text timeline notes. A repair job has one conclusion
 * (revised as work proceeds), not a log of competing opinions, so the
 * (repair_ticket_id) unique constraint makes the endpoint an upsert;
 * history is preserved on the ticket timeline, not by keeping superseded
 * rows. Parts consumed are modelled separately (ticket_lines) and are not
 * duplicated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_findings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->foreignId('repair_ticket_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('summary', 255);
            $table->text('details')->nullable();

            // Controlled vocabulary — App\Support\RepairFinding\RootCause.
            $table->string('root_cause', 32);

            // Component-level defects, a JSON array of Defect enum values.
            $table->json('defects')->nullable();

            // App\Support\RepairFinding\Resolution.
            $table->string('resolution', 32);

            $table->text('technician_notes')->nullable();

            $table->boolean('qc_passed')->nullable();
            $table->timestamp('qc_checked_at')->nullable();
            $table->foreignId('qc_checked_by_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->foreignId('recorded_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('root_cause');
            $table->index('resolution');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_findings');
    }
};
