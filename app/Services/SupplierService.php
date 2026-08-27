<?php

namespace App\Services;

use App\Models\Supplier;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierService
{
    public function __construct(private readonly SupplierRepositoryInterface $suppliers) {}

    public function list(): LengthAwarePaginator
    {
        return $this->suppliers->paginate();
    }

    public function create(array $data): Supplier
    {
        return $this->suppliers->create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        return $this->suppliers->update($supplier, $data);
    }

    public function delete(Supplier $supplier): bool
    {
        return $this->suppliers->delete($supplier);
    }
}
