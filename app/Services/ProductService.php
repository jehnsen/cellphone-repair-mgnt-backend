<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    public function list(): LengthAwarePaginator
    {
        return $this->products->paginate();
    }

    public function create(array $data): Product
    {
        $compatibleDeviceModelIds = $data['compatible_device_model_ids'] ?? null;
        unset($data['compatible_device_model_ids']);

        $product = $this->products->create($data);

        if ($compatibleDeviceModelIds !== null) {
            $product->compatibleDeviceModels()->sync($compatibleDeviceModelIds);
        }

        return $product;
    }

    public function update(Product $product, array $data): Product
    {
        $compatibleDeviceModelIds = $data['compatible_device_model_ids'] ?? null;
        unset($data['compatible_device_model_ids']);

        $product = $this->products->update($product, $data);

        if ($compatibleDeviceModelIds !== null) {
            $product->compatibleDeviceModels()->sync($compatibleDeviceModelIds);
        }

        return $product;
    }

    public function delete(Product $product): bool
    {
        return $this->products->delete($product);
    }
}
