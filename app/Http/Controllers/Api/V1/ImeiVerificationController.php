<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RepairTicket\OverrideImeiVerificationRequest;
use App\Http\Requests\Api\V1\RepairTicket\StoreImeiVerificationRequest;
use App\Http\Resources\ImeiVerificationResource;
use App\Models\RepairTicket;
use App\Services\ImeiVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImeiVerificationController extends Controller
{
    public function __construct(private readonly ImeiVerificationService $verifications) {}

    public function index(RepairTicket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        return ImeiVerificationResource::collection($this->verifications->list($ticket));
    }

    public function store(StoreImeiVerificationRequest $request, RepairTicket $ticket): JsonResponse
    {
        $verification = $this->verifications->verify($ticket, $request->validated(), $request->user());

        return (new ImeiVerificationResource($verification->load(['actor'])))->response()->setStatusCode(201);
    }

    public function override(OverrideImeiVerificationRequest $request, RepairTicket $ticket): JsonResponse
    {
        $verification = $this->verifications->override($ticket, $request->validated(), $request->user());

        return (new ImeiVerificationResource($verification->load(['actor', 'overriddenBy'])))->response()->setStatusCode(201);
    }
}
