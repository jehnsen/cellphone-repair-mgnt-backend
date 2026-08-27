<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RepairTicket\UpsertRepairFindingRequest;
use App\Http\Resources\RepairFindingResource;
use App\Models\RepairTicket;
use App\Services\RepairFindingService;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Http\JsonResponse;

class RepairFindingController extends Controller
{
    public function __construct(private readonly RepairFindingService $findings) {}

    public function show(RepairTicket $ticket): RepairFindingResource
    {
        $this->authorize('viewFinding', $ticket);

        $finding = $this->findings->find($ticket);

        if ($finding === null) {
            throw new ApiException(ErrorCode::NotFound, 'No findings have been recorded for this ticket yet.');
        }

        return new RepairFindingResource($finding);
    }

    public function upsert(UpsertRepairFindingRequest $request, RepairTicket $ticket): JsonResponse
    {
        $existed = $ticket->finding()->exists();

        $finding = $this->findings->upsert($ticket, $request->validated(), $request->user());

        return (new RepairFindingResource($finding->load(['recordedBy', 'qcCheckedBy'])))
            ->response()
            ->setStatusCode($existed ? 200 : 201);
    }
}
