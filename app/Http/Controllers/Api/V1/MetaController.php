<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RepairFindingService;
use Illuminate\Http\JsonResponse;

/**
 * Controlled vocabularies the frontend would otherwise have to hardcode a
 * second copy of. Server-side stays the source of truth. Only the repair
 * findings enums for now (root_cause / defects / resolution); other enum
 * groups can be folded in here as they need a client-facing surface.
 */
class MetaController extends Controller
{
    public function enums(): JsonResponse
    {
        return response()->json(['data' => RepairFindingService::enums()]);
    }
}
