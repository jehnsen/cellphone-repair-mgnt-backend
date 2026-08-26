<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceModel\StoreDeviceModelRequest;
use App\Http\Requests\Api\V1\DeviceModel\UpdateDeviceModelRequest;
use App\Http\Resources\DeviceModelResource;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Services\DeviceModelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DeviceModelController extends Controller
{
    public function __construct(private readonly DeviceModelService $deviceModels) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeviceModel::class);

        return DeviceModelResource::collection($this->deviceModels->list());
    }

    public function store(StoreDeviceModelRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['device_brand_id'] = DeviceBrand::idFromUlid($data['device_brand_ulid']);
        unset($data['device_brand_ulid']);

        $deviceModel = $this->deviceModels->create($data);

        return (new DeviceModelResource($deviceModel->load('brand')))->response()->setStatusCode(201);
    }

    public function show(DeviceModel $deviceModel): DeviceModelResource
    {
        $this->authorize('view', $deviceModel);

        return new DeviceModelResource($deviceModel->load('brand'));
    }

    public function update(UpdateDeviceModelRequest $request, DeviceModel $deviceModel): DeviceModelResource
    {
        $data = $request->validated();

        if (isset($data['device_brand_ulid'])) {
            $data['device_brand_id'] = DeviceBrand::idFromUlid($data['device_brand_ulid']);
            unset($data['device_brand_ulid']);
        }

        $deviceModel = $this->deviceModels->update($deviceModel, $data);

        return new DeviceModelResource($deviceModel->load('brand'));
    }

    public function destroy(DeviceModel $deviceModel): Response
    {
        $this->authorize('delete', $deviceModel);

        $this->deviceModels->delete($deviceModel);

        return response()->noContent();
    }
}
