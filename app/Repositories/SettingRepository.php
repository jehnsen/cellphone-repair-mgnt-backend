<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Not a BaseRepository: `settings` has no ulid and is addressed by the
 * (branch_id, key) composite unique, and the global (branch_id = null)
 * rows must be readable from every branch — so this does its own scoping
 * rather than inheriting BranchScope-style behaviour.
 */
class SettingRepository implements SettingRepositoryInterface
{
    public function globals(): Collection
    {
        return Setting::query()->whereNull('branch_id')->get()->keyBy('key');
    }

    public function forBranch(int $branchId): Collection
    {
        return Setting::query()->where('branch_id', $branchId)->get()->keyBy('key');
    }

    public function put(?int $branchId, string $key, mixed $value, string $type): Setting
    {
        return Setting::query()->updateOrCreate(
            ['branch_id' => $branchId, 'key' => $key],
            ['value' => $value, 'type' => $type],
        );
    }

    public function forget(?int $branchId, string $key): bool
    {
        return (bool) Setting::query()
            ->where('branch_id', $branchId)
            ->where('key', $key)
            ->delete();
    }
}
