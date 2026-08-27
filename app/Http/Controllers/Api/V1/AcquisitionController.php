<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Acquisition\CompleteAcquisitionRequest;
use App\Http\Requests\Api\V1\Acquisition\ImeiCheckAcquisitionRequest;
use App\Http\Requests\Api\V1\Acquisition\StoreAcquisitionRequest;
use App\Http\Requests\Api\V1\Acquisition\UpdateAcquisitionRequest;
use App\Http\Resources\AcquisitionResource;
use App\Models\Acquisition;
use App\Models\Branch;
use App\Models\Product;
use App\Services\AcquisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AcquisitionController extends Controller
{
    public function __construct(private readonly AcquisitionService $acquisitions) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Acquisition::class);

        return AcquisitionResource::collection($this->acquisitions->list());
    }

    public function store(StoreAcquisitionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['branch_id'] = Branch::idFromUlid($data['branch_ulid']);
        unset($data['branch_ulid']);

        $acquisition = $this->acquisitions->create($data, $request->user());

        return (new AcquisitionResource($acquisition))->response()->setStatusCode(201);
    }

    public function show(Acquisition $acquisition): AcquisitionResource
    {
        $this->authorize('view', $acquisition);

        return new AcquisitionResource($acquisition->load(['resultingSerializedUnit', 'processor']));
    }

    public function update(UpdateAcquisitionRequest $request, Acquisition $acquisition): AcquisitionResource
    {
        $acquisition = $this->acquisitions->update($acquisition, $request->validated());

        return new AcquisitionResource($acquisition);
    }

    public function imeiCheck(ImeiCheckAcquisitionRequest $request, Acquisition $acquisition): AcquisitionResource
    {
        $acquisition = $this->acquisitions->imeiCheck($acquisition, $request->validated());

        return new AcquisitionResource($acquisition);
    }

    public function complete(CompleteAcquisitionRequest $request, Acquisition $acquisition): AcquisitionResource
    {
        $data = $request->validated();
        $data['product_id'] = Product::idFromUlid($data['product_ulid']);
        unset($data['product_ulid']);

        $acquisition = $this->acquisitions->complete($acquisition, $data, $request->user());

        return new AcquisitionResource($acquisition);
    }
}
