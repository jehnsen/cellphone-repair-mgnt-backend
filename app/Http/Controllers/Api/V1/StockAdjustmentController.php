<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockAdjustment\StoreStockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\Branch;
use App\Models\Product;
use App\Models\SerializedUnit;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly StockAdjustmentService $adjustments) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockAdjustment::class);

        return StockAdjustmentResource::collection($this->adjustments->list());
    }

    public function store(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['branch_id'] = Branch::idFromUlid($data['branch_ulid']);
        unset($data['branch_ulid']);

        $data['lines'] = array_map(function (array $line) {
            $line['product_id'] = Product::idFromUlid($line['product_ulid']);
            $line['serialized_unit_id'] = isset($line['serialized_unit_ulid'])
                ? SerializedUnit::idFromUlid($line['serialized_unit_ulid'])
                : null;
            unset($line['product_ulid'], $line['serialized_unit_ulid']);

            return $line;
        }, $data['lines']);

        $adjustment = $this->adjustments->create($data, $request->user());

        return (new StockAdjustmentResource($adjustment))->response()->setStatusCode(201);
    }

    public function show(StockAdjustment $stockAdjustment): StockAdjustmentResource
    {
        $this->authorize('view', $stockAdjustment);

        return new StockAdjustmentResource($stockAdjustment->load([
            'lines.product',
            'lines.serializedUnit',
            'creator' => fn ($query) => $query->withoutGlobalScopes(),
        ]));
    }
}
