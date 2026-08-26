<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reporting endpoints read from these, never by scanning transactional
     * tables per request. Only daily_metrics is named in the brief; the
     * other three are proposed to satisfy that same rule for the other
     * report types (see docs/design/01-domain-design.md Flag 8).
     */
    public function up(): void
    {
        Schema::create('daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->decimal('gross_sales', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('vat_total', 14, 2)->default(0);
            $table->decimal('net_sales', 14, 2)->default(0);
            $table->decimal('cogs', 14, 2)->default(0);
            $table->decimal('gross_margin', 14, 2)->default(0);
            $table->unsignedInteger('tickets_received')->default(0);
            $table->unsignedInteger('tickets_released')->default(0);
            $table->decimal('refunds_total', 14, 2)->default(0);
            $table->timestamp('created_at')->nullable();

            $table->unique(['branch_id', 'business_date']);
        });

        Schema::create('technician_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('tickets_received')->default(0);
            $table->unsignedInteger('tickets_released')->default(0);
            $table->unsignedInteger('average_turnaround_minutes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['branch_id', 'technician_id', 'business_date'], 'tech_daily_metrics_unique');
        });

        Schema::create('warranty_failure_monthly', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('month');
            $table->unsignedInteger('units_installed')->default(0);
            $table->unsignedInteger('units_failed_within_30')->default(0);
            $table->unsignedInteger('units_failed_within_60')->default(0);
            $table->unsignedInteger('units_failed_within_90')->default(0);
            $table->decimal('failure_rate', 5, 2)->default(0);
            $table->timestamp('created_at')->nullable();

            $table->unique(['product_id', 'supplier_id', 'month']);
        });

        Schema::create('inventory_valuation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('snapshot_date');
            $table->decimal('total_cost_value', 14, 2)->default(0);
            $table->decimal('total_retail_value', 14, 2)->default(0);
            $table->unsignedInteger('sku_count')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->unique(['branch_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_valuation_snapshots');
        Schema::dropIfExists('warranty_failure_monthly');
        Schema::dropIfExists('technician_daily_metrics');
        Schema::dropIfExists('daily_metrics');
    }
};
