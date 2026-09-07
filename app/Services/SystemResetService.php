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
     * Every table `clearTransactionalData()` empties, in no particular
     * order (FK checks are disabled for the whole operation — see below).
     * Deliberately NOT `branches`, `settings`, `users`, `device_brands`,
     * `device_models`, `services`, `product_categories`, `products`,
     * `part_compatibilities`, `customers`, `customer_devices`,
     * `suppliers`, `message_templates`, `commission_rules`, or anything
     * roles/permissions — those are the shop's actual setup, not sample
     * data, and this endpoint exists specifically so a client doesn't have
     * to redo that setup after trying the system out. `activity_log` and
     * `personal_access_tokens` are left alone too — unrelated to the
     * domains this clears.
     *
     * @var list<string>
     */
    private const TRANSACTIONAL_TABLES = [
        // Repair tickets and everything nested under one.
        'warranty_claims', 'warranties', 'commission_entries', 'repair_findings',
        'imei_verifications', 'part_swaps', 'verification_tokens', 'unclaimed_notices',
        'ticket_quotes', 'ticket_photos', 'ticket_events', 'ticket_lines', 'repair_tickets',

        // POS: shifts, sales, payments, refunds, installments, sales
        // warranty, and the store-credit ledger it can settle into.
        'installment_schedules', 'installment_plans',
        'sale_warranty_claims', 'sale_warranties', 'supplier_returns',
        'refund_lines', 'refunds', 'discounts', 'payments', 'cash_movements',
        'sale_lines', 'sales', 'shifts',
        'store_credit_entries', 'store_credit_accounts',

        // Buy-back / refurb.
        'refurb_job_lines', 'refurb_jobs', 'acquisitions',

        // Inventory: receiving, adjustments, the movement ledger, the
        // cached balance it derives, and the serialized units themselves
        // (sample stock — a real client re-registers/receives their own).
        'goods_receipt_lines', 'goods_receipts',
        'purchase_order_lines', 'purchase_orders',
        'stock_adjustment_lines', 'stock_adjustments',
        'stock_movements', 'stock_levels', 'serialized_units',

        // Reporting rollups (unpopulated by the live ReportService today,
        // but cleared defensively in case anything ever writes to them).
        'daily_metrics', 'technician_daily_metrics',
        'warranty_failure_monthly', 'inventory_valuation_snapshots',

        // Misc history tied to the above.
        'document_prints', 'notification_logs',

        // Gapless document-number counters — cleared last so the next
        // ticket/sale/PO/etc. in each branch restarts at 0001 instead of
        // continuing from wherever the sample data left off.
        'sequences',
    ];

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

    /**
     * Empties every transactional table (repair tickets, POS, buy-back/
     * refurb, inventory ledger + serialized units, reporting rollups, and
     * the document-number sequences) while leaving the shop's actual setup
     * — branches, users, catalog, customers, suppliers — untouched. Meant
     * to be run once, by the shop owner, right before go-live, so a client
     * who evaluated the system with the demo dataset (or ran their own
     * trial transactions during setup) starts real operation with a clean
     * ledger and zeroed inventory counts instead of redoing branch/catalog
     * setup from scratch via fresh-install.
     *
     * Plain `DELETE FROM` per table (not `TRUNCATE`, which is DDL and
     * implicitly commits — that would break both the atomicity of this
     * operation and RefreshDatabase's per-test transaction). Internal ids
     * are never exposed to clients (see HasUlid), so leaving
     * AUTO_INCREMENT counters wherever they were is harmless; document
     * numbers are handled separately by clearing `sequences`.
     *
     * FK checks are disabled for the duration rather than hand-deriving a
     * topological delete order across ~40 tables with restrictOnDelete
     * FKs criss-crossing them (ticket_lines -> stock_movements ->
     * serialized_units -> acquisitions -> serialized_units again, etc.) —
     * every table in the list is emptied anyway, so ordering has no
     * observable effect other than risk of missing an edge.
     *
     * @return array{cleared: array<string, int>}
     */
    public function clearTransactionalData(): array
    {
        // Same guard as freshInstall() — this is destructive and, unlike
        // freshInstall(), plausible to fat-finger *after* go-live too.
        if (app()->isProduction() && ! (bool) config('app.allow_system_reset')) {
            throw new ApiException(
                ErrorCode::Forbidden,
                'Clearing transactional data is disabled in production. Set APP_ALLOW_SYSTEM_RESET=true to permit it.',
            );
        }

        $actorId = Auth::id();

        Log::warning('System clear-transactional-data requested', [
            'user_id' => $actorId,
            'connection' => DB::getDefaultConnection(),
            'database' => DB::connection()->getDatabaseName(),
        ]);

        $cleared = [];

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            DB::transaction(function () use (&$cleared): void {
                foreach (self::TRANSACTIONAL_TABLES as $table) {
                    $cleared[$table] = DB::table($table)->count();

                    DB::table($table)->delete();
                }
            });
        } catch (Throwable $e) {
            Log::error('System clear-transactional-data failed', [
                'user_id' => $actorId,
                'exception' => $e->getMessage(),
            ]);

            throw new ApiException(
                ErrorCode::InternalError,
                'Clearing transactional data did not complete. The database may be in a partial state — re-run it or restore from backup.',
            );
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Log::warning('System clear-transactional-data completed', [
            'user_id' => $actorId,
            'rows_cleared' => array_sum($cleared),
        ]);

        return ['cleared' => $cleared];
    }
}
