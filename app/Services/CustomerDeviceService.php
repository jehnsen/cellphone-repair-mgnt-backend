<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Repositories\Contracts\CustomerDeviceRepositoryInterface;
use Illuminate\Support\Collection;

class CustomerDeviceService
{
    public function __construct(private readonly CustomerDeviceRepositoryInterface $devices) {}

    public function listForCustomer(Customer $customer): Collection
    {
        return $customer->devices()->latest()->get();
    }

    public function create(Customer $customer, array $data): CustomerDevice
    {
        $data['customer_id'] = $customer->id;

        return $this->devices->create($data);
    }

    public function update(CustomerDevice $device, array $data): CustomerDevice
    {
        return $this->devices->update($device, $data);
    }

    public function delete(CustomerDevice $device): bool
    {
        return $this->devices->delete($device);
    }

    /**
     * The flagship differentiator: repair history for this physical device
     * across every customer who ever brought it in, not just the current
     * owner. See docs/design/01-domain-design.md Flag 6.
     */
    public function historyByImei(string $imeiNormalized): Collection
    {
        return $this->devices->findAllByImei($imeiNormalized);
    }
}
