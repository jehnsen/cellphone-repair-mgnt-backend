<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A walk-in customer may have no reachable phone number at intake time, so
 * `mobile` is no longer NOT NULL. The `(branch_id, mobile)` unique index
 * stays as-is: MariaDB (like MySQL) permits any number of NULL rows in a
 * unique index, so branchless-phone customers don't collide, while two
 * real customers still can't share a number within a branch. Raw SQL, not
 * ->change(), since doctrine/dbal isn't installed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE customers MODIFY mobile VARCHAR(13) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE customers MODIFY mobile VARCHAR(13) NOT NULL');
    }
};
