<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A part consumed by a refurb job doesn't fit any existing movement_type —
 * 'ticket_consumption' is specifically a repair-ticket concept, and a
 * refurb job isn't tied to a customer ticket. Widening the enum rather
 * than mislabeling it under an existing value.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY movement_type ENUM(
            'receipt', 'sale', 'return_in', 'return_out', 'ticket_consumption',
            'adjustment', 'transfer_in', 'transfer_out', 'write_off', 'refurb_consumption'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY movement_type ENUM(
            'receipt', 'sale', 'return_in', 'return_out', 'ticket_consumption',
            'adjustment', 'transfer_in', 'transfer_out', 'write_off'
        ) NOT NULL");
    }
};
