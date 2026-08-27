<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SerializedUnit\StoreSerializedUnitRequest;
use App\Http\Requests\Api\V1\SerializedUnit\UpdateSerializedUnitRequest;
use App\Http\Resources\SerializedUnitResource;
use App\Models\Branch;
use App\Models\Product;
use App\Models\SerializedUnit;
use App\Services\SerializedUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SerializedUnitController extends Controller
{
    public function __construct(private readonly SerializedUnitService $units) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SerializedUnit::class);

        return SerializedUnitResource::collection($this->units->list());
    }

    public function store(StoreSerializedUnitRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['product_id'] = Product::idFromUlid($data['product_ulid']);
        $data['branch_id'] = Branch::idFromUlid($data['branch_ulid']);
        unset($data['product_ulid'], $data['branch_ulid']);

        $unit = $this->units->create($data, $request->user());

        return (new SerializedUnitResource($unit->load('product')))->response()->setStatusCode(201);
    }

    public function show(SerializedUnit $serializedUnit): SerializedUnitResource
    {
        $this->authorize('view', $serializedUnit);

        return new SerializedUnitResource($serializedUnit->load('product'));
    }

    public function update(UpdateSerializedUnitRequest $request, SerializedUnit $serializedUnit): SerializedUnitResource
    {
        $unit = $this->units->update($serializedUnit, $request->validated(), $request->user());

        return new SerializedUnitResource($unit->load('product'));
    }
}
