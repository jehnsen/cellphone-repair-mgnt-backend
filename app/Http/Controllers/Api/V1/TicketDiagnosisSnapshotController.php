<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RepairTicket\StoreTicketDiagnosisSnapshotRequest;
use App\Http\Resources\TicketDiagnosisSnapshotResource;
use App\Models\RepairTicket;
use App\Models\TicketDiagnosisSnapshot;
use App\Services\DiagnosisVisualService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * What the customer was actually shown, frozen at the moment they agreed to
 * the quote — so the record keeps a picture rather than a live view that
 * moves when the finding behind it is revised.
 */
class TicketDiagnosisSnapshotController extends Controller
{
    public function __construct(private readonly DiagnosisVisualService $diagnosis) {}

    public function index(RepairTicket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        $snapshots = $this->diagnosis->snapshots($ticket)->each(
            fn (TicketDiagnosisSnapshot $snapshot) => $snapshot->signed_url = $this->diagnosis->signedUrl($snapshot)
        );

        return TicketDiagnosisSnapshotResource::collection($snapshots);
    }

    public function store(StoreTicketDiagnosisSnapshotRequest $request, RepairTicket $ticket): JsonResponse
    {
        $snapshot = $this->diagnosis->storeSnapshot(
            $ticket,
            $request->file('image'),
            $request->safe()->except('image'),
            $request->user(),
        );

        $snapshot->signed_url = $this->diagnosis->signedUrl($snapshot);

        return (new TicketDiagnosisSnapshotResource($snapshot))->response()->setStatusCode(201);
    }
}
