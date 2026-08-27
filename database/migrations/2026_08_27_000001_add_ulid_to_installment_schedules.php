<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The design doc calls installment_schedules "no ulid (nested under plan)"
 * but its own endpoint list then routes a specific schedule by
 * `{scheduleId}` — the only place in the whole design that would otherwise
 * expose an internal BIGINT in a URL (Rule 6). Adding a ulid here instead
 * of accepting that contradiction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installment_schedules', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable()->after('id');
        });

        DB::table('installment_schedules')->whereNull('ulid')->orderBy('id')->each(function ($row): void {
            DB::table('installment_schedules')->where('id', $row->id)->update(['ulid' => (string) Str::ulid()]);
        });

        Schema::table('installment_schedules', function (Blueprint $table): void {
            $table->unique('ulid');
        });
    }

    public function down(): void
    {
        Schema::table('installment_schedules', function (Blueprint $table): void {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
