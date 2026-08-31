<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sale\StoreRefundRequest;
use App\Http\Requests\Api\V1\Sale\StoreSalePaymentRequest;
use App\Http\Requests\Api\V1\Sale\StoreSaleRequest;
use App\Http\Requests\Api\V1\Sale\VoidSaleRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\RefundResource;
use App\Http\Resources\SaleResource;
use App\Models\Acquisition;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SerializedUnit;
use App\Models\Service;
use App\Services\PaymentRecorder;
use App\Services\RefundService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly PaymentRecorder $payments,
        private readonly RefundService $refunds,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sale::class);

        return SaleResource::collection($this->sales->list());
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['customer_ulid'])) {
            $data['customer_id'] = Customer::idFromUlid($data['customer_ulid']);
        }
        unset($data['customer_ulid']);

        $data['lines'] = array_map(function (array $line) {
            $line['product_id'] = isset($line['product_ulid']) ? Product::idFromUlid($line['product_ulid']) : null;
            $line['serialized_unit_id'] = isset($line['serialized_unit_ulid']) ? SerializedUnit::idFromUlid($line['serialized_unit_ulid']) : null;
            $line['service_id'] = isset($line['service_ulid']) ? Service::idFromUlid($line['service_ulid']) : null;
            unset($line['product_ulid'], $line['serialized_unit_ulid'], $line['service_ulid']);

            return $line;
        }, $data['lines']);

        $sale = $this->sales->create($data, $request->user());

        return (new SaleResource($sale))->response()->setStatusCode(201);
    }

    public function show(Sale $sale): SaleResource
    {
        $this->authorize('view', $sale);

        return new SaleResource($this->sales->show($sale));
    }

    public function void(VoidSaleRequest $request, Sale $sale): SaleResource
    {
        $sale = $this->sales->void($sale, $request->validated(), $request->user());

        return new SaleResource($sale->load(['lines', 'discounts', 'customer']));
    }

    public function addPayment(StoreSalePaymentRequest $request, Sale $sale): JsonResponse
    {
        return DB::transaction(function () use ($request, $sale) {
            $data = $request->validated();

            if (isset($data['acquisition_ulid'])) {
                $data['acquisition_id'] = Acquisition::idFromUlid($data['acquisition_ulid']);
            }
            unset($data['acquisition_ulid']);

            $alreadyPaid = (float) $sale->payments()->sum('amount');
            $payment = $this->payments->record(
                'sale',
                $sale->id,
                (float) $sale->total,
                $alreadyPaid,
                $data,
                $request->user(),
                $sale->shift,
            );

            return (new PaymentResource($payment->load('actor', 'acquisition')))->response()->setStatusCode(201);
        });
    }

    public function refund(StoreRefundRequest $request, Sale $sale): JsonResponse
    {
        $refund = $this->refunds->create($sale, $request->validated(), $request->user());

        return (new RefundResource($refund))->response()->setStatusCode(201);
    }
}
