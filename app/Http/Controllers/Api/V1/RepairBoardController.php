<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RepairTicket;
use App\Services\RepairBoardService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/tickets/board — open job orders grouped into status columns.
 *
 * Read-only. Moving a card is POST /tickets/{ticket}/transition, which
 * runs the state machine and the branch-capability check; this endpoint
 * only draws the board.
 */
class RepairBoardController extends Controller
{
    public function __construct(private readonly RepairBoardService $board) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', RepairTicket::class);

        return ApiResponse::success($this->board->board());
    }
}
