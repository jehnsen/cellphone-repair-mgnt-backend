<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface CustomerDeviceRepositoryInterface extends RepositoryInterface
{
    /** Every device row for this IMEI, across every customer who ever brought it in. */
    public function findAllByImei(string $imeiNormalized): Collection;
}
