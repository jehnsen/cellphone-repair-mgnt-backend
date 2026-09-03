<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog default for the shop-issued warranty on a sold unit — the "CP =
 * 1 year" the client asks for. `services.warranty_days` already exists for
 * repair labor; this is its sales-side counterpart. 0 = no warranty issued
 * at point of sale unless the cashier enters a term explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedSmallInteger('warranty_days')->default(0)->after('reorder_point');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('warranty_days');
        });
    }
};
