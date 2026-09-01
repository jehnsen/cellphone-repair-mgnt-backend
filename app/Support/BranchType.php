<?php

namespace App\Support;

/**
 * What a branch is allowed to do. Not every location runs a repair bench —
 * a `sales_only` branch is a pure retail counter (appliances, phones,
 * laptops, accessories): POS, inventory, and buy-backs work there, the
 * whole repair surface does not.
 *
 * Enforced by App\Policies\Concerns\ChecksBranchCapabilities.
 */
enum BranchType: string
{
    case RepairAndSales = 'repair_and_sales';
    case SalesOnly = 'sales_only';

    /** Whether job orders, the status board, part swaps, and refurb jobs exist here. */
    public function offersRepairs(): bool
    {
        return $this === self::RepairAndSales;
    }

    public function label(): string
    {
        return match ($this) {
            self::RepairAndSales => 'Repair and sales',
            self::SalesOnly => 'Sales only',
        };
    }
}
