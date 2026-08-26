<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Service\StoreServiceRequest;
use App\Http\Requests\Api\V1\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\ServiceCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceCatalogService $services) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Service::class);

        return ServiceResource::collection($this->services->list());
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->services->create($request->validated());

        return (new ServiceResource($service))->response()->setStatusCode(201);
    }

    public function show(Service $service): ServiceResource
    {
        $this->authorize('view', $service);

        return new ServiceResource($service);
    }

    public function update(UpdateServiceRequest $request, Service $service): ServiceResource
    {
        $service = $this->services->update($service, $request->validated());

        return new ServiceResource($service);
    }

    public function destroy(Service $service): Response
    {
        $this->authorize('delete', $service);

        $this->services->delete($service);

        return response()->noContent();
    }
}
