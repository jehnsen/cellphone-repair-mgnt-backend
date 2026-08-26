<?php

namespace App\Services;

use App\Models\Service;
use App\Repositories\Contracts\ServiceCatalogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceCatalogService
{
    public function __construct(private readonly ServiceCatalogRepositoryInterface $services) {}

    public function list(): LengthAwarePaginator
    {
        return $this->services->paginate();
    }

    public function create(array $data): Service
    {
        return $this->services->create($data);
    }

    public function update(Service $service, array $data): Service
    {
        return $this->services->update($service, $data);
    }

    public function delete(Service $service): bool
    {
        return $this->services->delete($service);
    }
}
