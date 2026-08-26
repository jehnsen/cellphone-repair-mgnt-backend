<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Product\StoreProductRequest;
use App\Http\Requests\Api\V1\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        return ProductResource::collection($this->products->list());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $this->resolveUlidReferences($request->validated());

        $product = $this->products->create($data);

        return (new ProductResource($product->load(['category', 'brand'])))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return new ProductResource($product->load(['category', 'brand']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $data = $this->resolveUlidReferences($request->validated());

        $product = $this->products->update($product, $data);

        return new ProductResource($product->load(['category', 'brand']));
    }

    public function destroy(Product $product): Response
    {
        $this->authorize('delete', $product);

        $this->products->delete($product);

        return response()->noContent();
    }

    private function resolveUlidReferences(array $data): array
    {
        if (isset($data['product_category_ulid'])) {
            $data['product_category_id'] = ProductCategory::idFromUlid($data['product_category_ulid']);
        }
        unset($data['product_category_ulid']);

        if (isset($data['device_brand_ulid'])) {
            $data['device_brand_id'] = DeviceBrand::idFromUlid($data['device_brand_ulid']);
        }
        unset($data['device_brand_ulid']);

        if (isset($data['compatible_device_model_ulids'])) {
            $data['compatible_device_model_ids'] = DeviceModel::query()
                ->whereIn('ulid', $data['compatible_device_model_ulids'])
                ->pluck('id')
                ->all();
        }
        unset($data['compatible_device_model_ulids']);

        return $data;
    }
}
