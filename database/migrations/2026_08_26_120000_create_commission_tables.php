<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('role', 30)->nullable();
            $table->enum('basis', ['flat', 'percent_of_labor', 'percent_of_margin']);
            $table->decimal('value', 14, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        // Append-only; a reversal is a new signed row referencing the
        // original, never an update.
        Schema::create('commission_entries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('repair_ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('commission_rule_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['pending', 'payable', 'paid', 'reversed'])->default('pending');
            $table->foreignId('reverses_entry_id')->nullable()->constrained('commission_entries')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('commission_rules');
    }
};
