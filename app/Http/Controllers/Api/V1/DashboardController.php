<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/dashboard — the landing summary.
 *
 * Detail level follows the caller: `reports.margin.view` (owner/manager)
 * gets stock valuation; everyone else (the cashier) gets counts and
 * today's takings only. Scope follows BranchContext — own branch by
 * default, `?branch=all` for an owner holding branches.view_all, which is
 * what produces the both-branches-in-one-view the shop asked for.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(
            (bool) $request->user()?->can('reports.view'),
            403,
            'You do not have permission to view the dashboard.',
        );

        return ApiResponse::success(
            $this->dashboard->summary(
                includeFinancials: (bool) $request->user()->can('reports.margin.view'),
            ),
        );
    }
}
