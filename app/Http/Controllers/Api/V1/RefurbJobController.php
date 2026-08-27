<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RefurbJob\StoreRefurbJobLineRequest;
use App\Http\Requests\Api\V1\RefurbJob\StoreRefurbJobRequest;
use App\Http\Resources\RefurbJobLineResource;
use App\Http\Resources\RefurbJobResource;
use App\Models\Acquisition;
use App\Models\Product;
use App\Models\RefurbJob;
use App\Services\RefurbJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RefurbJobController extends Controller
{
    public function __construct(private readonly RefurbJobService $jobs) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RefurbJob::class);

        return RefurbJobResource::collection($this->jobs->list());
    }

    public function store(StoreRefurbJobRequest $request): JsonResponse
    {
        $data = $request->validated();
        $acquisition = Acquisition::where('ulid', $data['acquisition_ulid'])->firstOrFail();

        $job = $this->jobs->create($data, $acquisition);

        return (new RefurbJobResource($job))->response()->setStatusCode(201);
    }

    public function show(RefurbJob $refurbJob): RefurbJobResource
    {
        $this->authorize('view', $refurbJob);

        return new RefurbJobResource($refurbJob->load(['acquisition', 'serializedUnit.product', 'lines.product']));
    }

    public function addLine(StoreRefurbJobLineRequest $request, RefurbJob $refurbJob): JsonResponse
    {
        $data = $request->validated();
        $data['product_id'] = Product::idFromUlid($data['product_ulid']);
        unset($data['product_ulid']);

        $line = $this->jobs->addLine($refurbJob, $data, $request->user());

        return (new RefurbJobLineResource($line))->response()->setStatusCode(201);
    }

    public function complete(RefurbJob $refurbJob): RefurbJobResource
    {
        $this->authorize('complete', $refurbJob);

        $job = $this->jobs->complete($refurbJob);

        return new RefurbJobResource($job);
    }
}
