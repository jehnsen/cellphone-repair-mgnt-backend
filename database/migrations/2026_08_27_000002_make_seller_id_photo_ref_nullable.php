<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * There's no photo-upload endpoint for acquisitions yet (scoped out —
 * seller_id_photo_ref is a plain string reference for now, not a Rule
 * Zero ULID+signed-URL pair like ticket_photos), so nothing can actually
 * supply this at intake time. NOT NULL without a way to satisfy it blocked
 * every acquisition from being created at all. Raw SQL, not ->change(),
 * since doctrine/dbal isn't installed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE acquisitions MODIFY seller_id_photo_ref VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acquisitions MODIFY seller_id_photo_ref VARCHAR(255) NOT NULL');
    }
};
