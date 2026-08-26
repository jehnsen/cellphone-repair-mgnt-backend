<?php

namespace App\Services;

use App\Models\DeviceModel;
use App\Repositories\Contracts\DeviceModelRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeviceModelService
{
    public function __construct(private readonly DeviceModelRepositoryInterface $deviceModels) {}

    public function list(): LengthAwarePaginator
    {
        return $this->deviceModels->paginate();
    }

    public function create(array $data): DeviceModel
    {
        return $this->deviceModels->create($data);
    }

    public function update(DeviceModel $deviceModel, array $data): DeviceModel
    {
        return $this->deviceModels->update($deviceModel, $data);
    }

    public function delete(DeviceModel $deviceModel): bool
    {
        return $this->deviceModels->delete($deviceModel);
    }
}
