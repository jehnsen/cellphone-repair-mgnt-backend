<?php

namespace App\Services;

use App\Models\ProductCategory;
use App\Repositories\Contracts\ProductCategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductCategoryService
{
    public function __construct(private readonly ProductCategoryRepositoryInterface $categories) {}

    public function list(): LengthAwarePaginator
    {
        return $this->categories->paginate();
    }

    public function create(array $data): ProductCategory
    {
        return $this->categories->create($data);
    }

    public function update(ProductCategory $category, array $data): ProductCategory
    {
        return $this->categories->update($category, $data);
    }

    public function delete(ProductCategory $category): bool
    {
        return $this->categories->delete($category);
    }
}
