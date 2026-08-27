<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Shift\CloseShiftRequest;
use App\Http\Requests\Api\V1\Shift\OpenShiftRequest;
use App\Http\Requests\Api\V1\Shift\StoreCashMovementRequest;
use App\Http\Resources\CashMovementResource;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $shifts) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Shift::class);

        return ShiftResource::collection($this->shifts->list());
    }

    public function open(OpenShiftRequest $request): JsonResponse
    {
        $shift = $this->shifts->open($request->validated(), $request->user());

        return (new ShiftResource($shift->load('cashier')))->response()->setStatusCode(201);
    }

    public function show(Shift $shift): ShiftResource
    {
        $this->authorize('view', $shift);

        return new ShiftResource($shift->load('cashier'));
    }

    public function close(CloseShiftRequest $request, Shift $shift): ShiftResource
    {
        $shift = $this->shifts->close($shift, $request->validated(), $request->user());

        return new ShiftResource($shift->load('cashier'));
    }

    public function addCashMovement(StoreCashMovementRequest $request, Shift $shift): JsonResponse
    {
        $movement = $this->shifts->addCashMovement($shift, $request->validated(), $request->user());

        return (new CashMovementResource($movement->load('actor')))->response()->setStatusCode(201);
    }
}
