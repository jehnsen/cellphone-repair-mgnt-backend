<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductCategory\StoreProductCategoryRequest;
use App\Http\Requests\Api\V1\ProductCategory\UpdateProductCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductCategoryController extends Controller
{
    public function __construct(private readonly ProductCategoryService $categories) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductCategory::class);

        return ProductCategoryResource::collection($this->categories->list());
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['parent_ulid'])) {
            $data['parent_id'] = ProductCategory::idFromUlid($data['parent_ulid']);
        }
        unset($data['parent_ulid']);

        $category = $this->categories->create($data);

        return (new ProductCategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(ProductCategory $productCategory): ProductCategoryResource
    {
        $this->authorize('view', $productCategory);

        return new ProductCategoryResource($productCategory);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): ProductCategoryResource
    {
        $data = $request->validated();

        if (isset($data['parent_ulid'])) {
            $data['parent_id'] = ProductCategory::idFromUlid($data['parent_ulid']);
        }
        unset($data['parent_ulid']);

        $productCategory = $this->categories->update($productCategory, $data);

        return new ProductCategoryResource($productCategory);
    }

    public function destroy(ProductCategory $productCategory): Response
    {
        $this->authorize('delete', $productCategory);

        $this->categories->delete($productCategory);

        return response()->noContent();
    }
}
