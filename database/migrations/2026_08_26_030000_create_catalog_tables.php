<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_brands', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name')->unique();
            $table->string('logo_ref')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('device_models', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('device_brand_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->smallInteger('release_year')->nullable();
            $table->json('aliases')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['device_brand_id', 'name']);
            $table->fullText('name');
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('category', 60)->nullable();
            $table->decimal('default_price', 14, 2)->default(0);
            $table->integer('default_duration_minutes')->nullable();
            $table->smallInteger('warranty_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('sku', 40)->unique();
            $table->string('barcode', 64)->unique()->nullable();
            $table->string('name');
            $table->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('device_brand_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('type', ['handset', 'accessory', 'part']);
            $table->decimal('cost', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->boolean('is_serialized')->default(false);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->fullText('name');
        });

        Schema::create('part_compatibilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('device_model_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'device_model_id']);
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_cost_nonneg CHECK (cost >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_selling_price_nonneg CHECK (selling_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('part_compatibilities');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('services');
        Schema::dropIfExists('device_models');
        Schema::dropIfExists('device_brands');
    }
};
