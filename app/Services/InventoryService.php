<?php

namespace App\Services;

use App\Repositories\Contracts\StockLevelRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\CursorPaginator;

class InventoryService
{
    public function __construct(
        private readonly StockLevelRepositoryInterface $levels,
        private readonly StockMovementRepositoryInterface $movements,
    ) {}

    public function levels(): LengthAwarePaginator
    {
        return $this->levels->paginate();
    }

    public function movements(): CursorPaginator
    {
        return $this->movements->ledger();
    }
}
