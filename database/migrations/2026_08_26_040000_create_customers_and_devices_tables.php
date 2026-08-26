<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('mobile', 13);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'mobile']);
            $table->fullText('name');
        });

        // A device's history is queryable by IMEI regardless of which
        // customer brought it in — imei_normalized is deliberately NOT
        // unique, since the same physical phone gets a new row each time it
        // changes hands (see docs/design/01-domain-design.md Flag 6).
        Schema::create('customer_devices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('device_model_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('imei_normalized', 15)->nullable();
            $table->string('serial_number', 40)->nullable();
            $table->string('color', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('imei_normalized');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_devices');
        Schema::dropIfExists('customers');
    }
};
