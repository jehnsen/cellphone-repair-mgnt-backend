<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleWarrantyResource;
use App\Models\Sale;
use App\Models\SaleWarranty;
use App\Models\SerializedUnit;
use App\Services\SaleWarrantyService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleWarrantyController extends Controller
{
    public function __construct(private readonly SaleWarrantyService $warranties) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SaleWarranty::class);

        return SaleWarrantyResource::collection($this->warranties->list());
    }

    public function show(SaleWarranty $saleWarranty): SaleWarrantyResource
    {
        $this->authorize('view', $saleWarranty);

        return new SaleWarrantyResource($this->warranties->show($saleWarranty));
    }

    /** GET /sales/{sale}/warranties — every warranty a sale issued. */
    public function forSale(Sale $sale): AnonymousResourceCollection
    {
        $this->authorize('view', $sale);

        return SaleWarrantyResource::collection($this->warranties->forSale($sale));
    }

    /** GET /serialized-units/{serializedUnit}/warranties — a unit's warranty history. */
    public function forUnit(SerializedUnit $serializedUnit): AnonymousResourceCollection
    {
        $this->authorize('view', $serializedUnit);

        return SaleWarrantyResource::collection($this->warranties->forUnit($serializedUnit));
    }
}
