<?php

namespace App\Repositories;

use App\Models\CustomerDevice;
use App\Repositories\Contracts\CustomerDeviceRepositoryInterface;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedFilter;

class CustomerDeviceRepository extends BaseRepository implements CustomerDeviceRepositoryInterface
{
    protected function modelClass(): string
    {
        return CustomerDevice::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('customer_id'),
            AllowedFilter::exact('device_model_id'),
            'imei_normalized',
            'serial_number',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    public function findAllByImei(string $imeiNormalized): Collection
    {
        // Deliberately bypasses BranchScope on the eager-loaded customer:
        // this endpoint exists specifically so a repeat repair is
        // recognized even when the customer's first visit was at a
        // different branch (see docs/design/01-domain-design.md §2.3 —
        // "regardless of which customer brought it in").
        return CustomerDevice::with(['customer' => fn ($query) => $query->withoutGlobalScopes()])
            ->where('imei_normalized', $imeiNormalized)
            ->orderByDesc('created_at')
            ->get();
    }
}
