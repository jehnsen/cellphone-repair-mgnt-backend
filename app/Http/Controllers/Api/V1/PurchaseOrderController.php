<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PurchaseOrder\ReceivePurchaseOrderRequest;
use App\Http\Requests\Api\V1\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\Api\V1\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Resources\GoodsReceiptResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $orders) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        return PurchaseOrderResource::collection($this->orders->list());
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['branch_id'] = Branch::idFromUlid($data['branch_ulid']);
        $data['supplier_id'] = Supplier::idFromUlid($data['supplier_ulid']);
        unset($data['branch_ulid'], $data['supplier_ulid']);

        $data['lines'] = array_map(function (array $line) {
            $line['product_id'] = Product::idFromUlid($line['product_ulid']);
            unset($line['product_ulid']);

            return $line;
        }, $data['lines']);

        $po = $this->orders->create($data, $request->user());

        return (new PurchaseOrderResource($po))->response()->setStatusCode(201);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('view', $purchaseOrder);

        return new PurchaseOrderResource($purchaseOrder->load(['lines.product', 'supplier']));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $po = $this->orders->update($purchaseOrder, $request->validated());

        return new PurchaseOrderResource($po);
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $data = $request->validated();
        $data['lines'] = array_map(function (array $line) {
            $line['product_id'] = Product::idFromUlid($line['product_ulid']);
            unset($line['product_ulid']);

            return $line;
        }, $data['lines']);

        $receipt = $this->orders->receive($purchaseOrder, $data, $request->user());

        return (new GoodsReceiptResource($receipt))->response()->setStatusCode(201);
    }
}
