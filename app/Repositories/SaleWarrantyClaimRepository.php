<?php

namespace App\Repositories;

use App\Models\SaleWarrantyClaim;
use App\Repositories\Contracts\SaleWarrantyClaimRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SaleWarrantyClaimRepository extends BaseRepository implements SaleWarrantyClaimRepositoryInterface
{
    protected function modelClass(): string
    {
        return SaleWarrantyClaim::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with([
            'warranty',
            'serializedUnit.product',
            'repairTicket' => fn ($query) => $query->withoutGlobalScopes(),
            'supplierReturn',
        ]);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('status'),
            AllowedFilter::exact('resolution'),
            AllowedFilter::exact('handling'),
            AllowedFilter::exact('serialized_unit_id'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at'];
    }
}
