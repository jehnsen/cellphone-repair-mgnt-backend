<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    public function __construct(private readonly CustomerRepositoryInterface $customers) {}

    public function list(): LengthAwarePaginator
    {
        return $this->customers->paginate();
    }

    public function create(array $data): Customer
    {
        return $this->customers->create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        return $this->customers->update($customer, $data);
    }

    public function delete(Customer $customer): bool
    {
        return $this->customers->delete($customer);
    }
}
