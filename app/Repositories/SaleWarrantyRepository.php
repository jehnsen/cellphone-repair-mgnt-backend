<?php

namespace App\Repositories;

use App\Models\SaleWarranty;
use App\Repositories\Contracts\SaleWarrantyRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SaleWarrantyRepository extends BaseRepository implements SaleWarrantyRepositoryInterface
{
    protected function modelClass(): string
    {
        return SaleWarranty::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        // The customer can legitimately belong to another branch (a repeat
        // buyer) — load it outside BranchScope or the resource nulls it out.
        return parent::filteredQuery()->with([
            'serializedUnit.product',
            'customer' => fn ($query) => $query->withoutGlobalScopes(),
        ]);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('coverage'),
            AllowedFilter::exact('serialized_unit_id'),
            AllowedFilter::exact('customer_id'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'expiry_date'];
    }
}
