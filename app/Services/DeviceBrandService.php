<?php

namespace App\Services;

use App\Models\DeviceBrand;
use App\Repositories\Contracts\DeviceBrandRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeviceBrandService
{
    public function __construct(private readonly DeviceBrandRepositoryInterface $brands) {}

    public function list(): LengthAwarePaginator
    {
        return $this->brands->paginate();
    }

    public function create(array $data): DeviceBrand
    {
        return $this->brands->create($data);
    }

    public function update(DeviceBrand $brand, array $data): DeviceBrand
    {
        return $this->brands->update($brand, $data);
    }

    public function delete(DeviceBrand $brand): bool
    {
        return $this->brands->delete($brand);
    }
}
