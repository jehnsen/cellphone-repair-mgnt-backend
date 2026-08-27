<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GoodsReceipt\StoreGoodsReceiptRequest;
use App\Http\Resources\GoodsReceiptResource;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GoodsReceiptController extends Controller
{
    public function __construct(private readonly GoodsReceiptService $receipts) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        return GoodsReceiptResource::collection($this->receipts->list());
    }

    public function store(StoreGoodsReceiptRequest $request): JsonResponse
    {
        $data = $request->validated();
        $branchId = Branch::idFromUlid($data['branch_ulid']);
        $supplierId = Supplier::idFromUlid($data['supplier_ulid']);

        $lines = array_map(fn (array $line) => [
            'product_id' => Product::idFromUlid($line['product_ulid']),
            'quantity' => $line['quantity'],
            'unit_cost' => $line['unit_cost'],
        ], $data['lines']);

        $receipt = $this->receipts->post($branchId, null, $supplierId, $lines, $request->user());

        return (new GoodsReceiptResource($receipt))->response()->setStatusCode(201);
    }

    public function show(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('view', $goodsReceipt);

        return new GoodsReceiptResource($goodsReceipt->load(['lines.product', 'supplier', 'receiver']));
    }
}
