<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imei_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('phase', ['intake', 'pre_repair', 'post_repair', 'release']);
            $table->char('scanned_imei', 15);
            $table->boolean('matches_expected');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('override_reason')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('part_swaps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('removed_description', 160);
            $table->string('removed_serial', 60)->nullable();
            $table->string('removed_photo_ref')->nullable();
            $table->foreignId('installed_product_id')->constrained('products')->restrictOnDelete();
            $table->string('installed_serial', 60)->nullable();
            $table->enum('disposition', ['returned_to_customer', 'retained_for_disposal', 'returned_to_supplier']);
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        // The token itself is the public identifier backing the
        // unauthenticated GET /public/verify/{token} — unguessable, not
        // sequential.
        Schema::create('verification_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('token', 32)->unique();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_tokens');
        Schema::dropIfExists('part_swaps');
        Schema::dropIfExists('imei_verifications');
    }
};
