<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\System\FreshInstallRequest;
use App\Services\SystemResetService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/system/fresh-install — one-shot database reset for a new
 * client deployment. Drops every table, re-runs all migrations, and
 * re-seeds only the baseline (roles/permissions, the shop branch,
 * shop-wide settings, staff accounts, catalog) — none of the demo data
 * the dev seeders carry.
 *
 * Owner-only and confirmation-gated (see FreshInstallRequest), rate
 * limited to a trickle (see routes/api.php), and refused in production
 * unless APP_ALLOW_SYSTEM_RESET=true (see SystemResetService).
 */
class SystemController extends Controller
{
    public function __construct(private readonly SystemResetService $reset) {}

    public function freshInstall(FreshInstallRequest $request): JsonResponse
    {
        $result = $this->reset->freshInstall();

        return ApiResponse::success([
            'status' => 'reset_complete',
            'tables_recreated' => $result['tables_recreated'],
            'seeded' => ['roles', 'permissions', 'branch', 'settings', 'users', 'catalog'],
            'message' => 'The database was reset to a fresh install. Log in again — existing tokens are gone.',
        ]);
    }
}
