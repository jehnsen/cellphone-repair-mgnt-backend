<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceBrand\StoreDeviceBrandRequest;
use App\Http\Requests\Api\V1\DeviceBrand\UpdateDeviceBrandRequest;
use App\Http\Resources\DeviceBrandResource;
use App\Models\DeviceBrand;
use App\Services\DeviceBrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DeviceBrandController extends Controller
{
    public function __construct(private readonly DeviceBrandService $brands) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeviceBrand::class);

        return DeviceBrandResource::collection($this->brands->list());
    }

    public function store(StoreDeviceBrandRequest $request): JsonResponse
    {
        $brand = $this->brands->create($request->validated());

        return (new DeviceBrandResource($brand))->response()->setStatusCode(201);
    }

    public function show(DeviceBrand $deviceBrand): DeviceBrandResource
    {
        $this->authorize('view', $deviceBrand);

        return new DeviceBrandResource($deviceBrand);
    }

    public function update(UpdateDeviceBrandRequest $request, DeviceBrand $deviceBrand): DeviceBrandResource
    {
        $deviceBrand = $this->brands->update($deviceBrand, $request->validated());

        return new DeviceBrandResource($deviceBrand);
    }

    public function destroy(DeviceBrand $deviceBrand): Response
    {
        $this->authorize('delete', $deviceBrand);

        $this->brands->delete($deviceBrand);

        return response()->noContent();
    }
}
