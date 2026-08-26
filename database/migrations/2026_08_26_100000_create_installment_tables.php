<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->decimal('principal', 14, 2);
            $table->decimal('downpayment', 14, 2)->default(0);
            $table->smallInteger('term_months');
            $table->string('schedule_rule', 30)->default('monthly');
            $table->enum('status', ['active', 'completed', 'defaulted', 'cancelled'])->default('active');
            $table->timestamps();
        });

        Schema::create('installment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount_due', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'overdue', 'waived'])->default('pending');
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_schedules');
        Schema::dropIfExists('installment_plans');
    }
};
