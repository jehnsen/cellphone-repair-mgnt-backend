<?php

namespace App\Repositories\Contracts;

use App\Models\Setting;
use Illuminate\Support\Collection;

interface SettingRepositoryInterface
{
    /** Global rows (branch_id = null), keyed by `key`. */
    public function globals(): Collection;

    /** Rows for one branch, keyed by `key`. */
    public function forBranch(int $branchId): Collection;

    /**
     * Insert or update one (branch_id, key) row and return it.
     */
    public function put(?int $branchId, string $key, mixed $value, string $type): Setting;

    /** Delete one (branch_id, key) row; true if a row was removed. */
    public function forget(?int $branchId, string $key): bool;
}
