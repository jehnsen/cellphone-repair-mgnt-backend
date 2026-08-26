<?php

namespace App\Repositories\Contracts;

use App\Models\Product;

interface ProductRepositoryInterface extends RepositoryInterface
{
    public function findBySku(string $sku): ?Product;
}
