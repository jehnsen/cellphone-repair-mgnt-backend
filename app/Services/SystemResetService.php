<?php

namespace App\Services;

use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Database\Seeders\BaseInstallSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wipes the database back to a brand-new-install state and re-seeds only
 * the baseline (BaseInstallSeeder: roles/permissions, the shop branch,
 * shop-wide settings, staff accounts, and the full catalog).
 *
 * Backs POST /api/v1/system/fresh-install — meant to be run once, by the
 * shop owner, right after deploying the API for a new client, to clear the
 * demo dataset that ships in the dev seeders.
 *
 * Implementation is `migrate:fresh` (drop every table, re-run all
 * migrations) followed by the baseline seeder — the same thing
 * `php artisan migrate:fresh --seed` does at the console, minus the demo
 * data. `migrate:fresh` is used rather than truncating tables by hand so
 * FULLTEXT indexes, CHECK constraints, and FK ordering are recreated
 * exactly as the migrations define them.
 */
class SystemResetService
{
    /**
     * @return array{tables_recreated: int}
     */
    public function freshInstall(): array
    {
        // Never let this run against a production database unless it's been
        // explicitly opted in — a fat-fingered call here is unrecoverable.
        if (app()->isProduction() && ! (bool) config('app.allow_system_reset')) {
            throw new ApiException(
                ErrorCode::Forbidden,
                'A system reset is disabled in production. Set APP_ALLOW_SYSTEM_RESET=true to permit it.',
            );
        }

        $actorId = Auth::id();

        Log::warning('System fresh-install requested', [
            'user_id' => $actorId,
            'connection' => DB::getDefaultConnection(),
            'database' => DB::connection()->getDatabaseName(),
        ]);

        try {
            // --force: skip the interactive "are you sure" prompt (there's
            // no TTY here). --seed is omitted on purpose so DatabaseSeeder
            // (the demo dataset) never runs.
            Artisan::call('migrate:fresh', ['--force' => true]);

            Artisan::call('db:seed', [
                '--class' => BaseInstallSeeder::class,
                '--force' => true,
            ]);
        } catch (Throwable $e) {
            Log::error('System fresh-install failed', [
                'user_id' => $actorId,
                'exception' => $e->getMessage(),
            ]);

            throw new ApiException(
                ErrorCode::InternalError,
                'The database reset did not complete. The database may be in a partial state — re-run the reset or restore from backup.',
            );
        }

        $tables = DB::select('SHOW TABLES');

        Log::warning('System fresh-install completed', [
            'user_id' => $actorId,
            'tables_recreated' => count($tables),
        ]);

        return ['tables_recreated' => count($tables)];
    }
}
