<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalesWarranty\ResolveSaleWarrantyClaimRequest;
use App\Http\Requests\Api\V1\SalesWarranty\StoreSaleWarrantyClaimRequest;
use App\Http\Resources\SaleWarrantyClaimResource;
use App\Models\SaleWarranty;
use App\Models\SaleWarrantyClaim;
use App\Services\SaleWarrantyClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleWarrantyClaimController extends Controller
{
    public function __construct(private readonly SaleWarrantyClaimService $claims) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SaleWarrantyClaim::class);

        return SaleWarrantyClaimResource::collection($this->claims->list());
    }

    public function show(SaleWarrantyClaim $saleWarrantyClaim): SaleWarrantyClaimResource
    {
        $this->authorize('view', $saleWarrantyClaim);

        return new SaleWarrantyClaimResource($this->claims->show($saleWarrantyClaim));
    }

    /** POST /sale-warranties/{saleWarranty}/claims */
    public function store(StoreSaleWarrantyClaimRequest $request, SaleWarranty $saleWarranty): JsonResponse
    {
        $claim = $this->claims->file($saleWarranty, $request->validated(), $request->user());

        return (new SaleWarrantyClaimResource($claim))->response()->setStatusCode(201);
    }

    public function resolve(ResolveSaleWarrantyClaimRequest $request, SaleWarrantyClaim $saleWarrantyClaim): SaleWarrantyClaimResource
    {
        $claim = $this->claims->resolve($saleWarrantyClaim, $request->validated(), $request->user());

        return new SaleWarrantyClaimResource($claim);
    }
}
