<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InstallmentPlan\PayInstallmentScheduleRequest;
use App\Http\Requests\Api\V1\InstallmentPlan\StoreInstallmentPlanRequest;
use App\Http\Resources\InstallmentPlanResource;
use App\Http\Resources\InstallmentScheduleResource;
use App\Models\InstallmentPlan;
use App\Models\InstallmentSchedule;
use App\Models\Sale;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Services\InstallmentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstallmentPlanController extends Controller
{
    public function __construct(
        private readonly InstallmentPlanService $plans,
        private readonly ShiftRepositoryInterface $shifts,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InstallmentPlan::class);

        return InstallmentPlanResource::collection($this->plans->list());
    }

    public function store(StoreInstallmentPlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $sale = Sale::where('ulid', $data['sale_ulid'])->firstOrFail();

        $plan = $this->plans->create($sale, $data);

        return (new InstallmentPlanResource($plan))->response()->setStatusCode(201);
    }

    public function show(InstallmentPlan $installmentPlan): InstallmentPlanResource
    {
        $this->authorize('view', $installmentPlan);

        return new InstallmentPlanResource($this->plans->show($installmentPlan));
    }

    public function pay(PayInstallmentScheduleRequest $request, InstallmentPlan $installmentPlan, InstallmentSchedule $schedule): InstallmentScheduleResource
    {
        abort_if($schedule->installment_plan_id !== $installmentPlan->id, 404);

        $shift = $this->shifts->findOpenFor($request->user());
        $schedule = $this->plans->pay($installmentPlan, $schedule, $request->validated(), $request->user(), $shift);

        return new InstallmentScheduleResource($schedule);
    }
}
