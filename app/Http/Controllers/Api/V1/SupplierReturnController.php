<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalesWarranty\CloseSupplierReturnRequest;
use App\Http\Requests\Api\V1\SalesWarranty\StoreSupplierReturnRequest;
use App\Http\Resources\SupplierReturnResource;
use App\Models\SaleWarrantyClaim;
use App\Models\SerializedUnit;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Services\SupplierReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierReturnController extends Controller
{
    public function __construct(private readonly SupplierReturnService $returns) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SupplierReturn::class);

        return SupplierReturnResource::collection($this->returns->list());
    }

    public function store(StoreSupplierReturnRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['serialized_unit_id'] = SerializedUnit::idFromUlid($data['serialized_unit_ulid']);
        $data['supplier_id'] = Supplier::idFromUlid($data['supplier_ulid']);
        if (isset($data['sale_warranty_claim_ulid'])) {
            $data['sale_warranty_claim_id'] = SaleWarrantyClaim::idFromUlid($data['sale_warranty_claim_ulid']);
        }
        unset($data['serialized_unit_ulid'], $data['supplier_ulid'], $data['sale_warranty_claim_ulid']);

        $return = $this->returns->create($data, $request->user());

        return (new SupplierReturnResource($return))->response()->setStatusCode(201);
    }

    public function show(SupplierReturn $supplierReturn): SupplierReturnResource
    {
        $this->authorize('view', $supplierReturn);

        return new SupplierReturnResource($this->returns->show($supplierReturn));
    }

    public function close(CloseSupplierReturnRequest $request, SupplierReturn $supplierReturn): SupplierReturnResource
    {
        $return = $this->returns->close($supplierReturn, $request->validated(), $request->user());

        return new SupplierReturnResource($return);
    }
}
