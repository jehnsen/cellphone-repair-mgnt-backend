<?php

namespace App\Services;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Branch-scoped settings with a shop-wide fallback. `resolved()` is what a
 * settings screen reads — every key merged, branch row winning over the
 * global default, each entry tagged with where it came from. `apply()` is
 * a partial bulk upsert: only the keys in the payload are touched, always
 * against the caller's own branch (the global rows are seed/console-managed
 * for now, not writable through the API).
 */
class SettingService
{
    public function __construct(private readonly SettingRepositoryInterface $settings) {}

    /**
     * @return array<int, array{key: string, value: mixed, type: string, source: string, overridable: bool}>
     */
    public function resolved(int $branchId): array
    {
        $globals = $this->settings->globals();
        $branch = $this->settings->forBranch($branchId);

        $keys = $globals->keys()->merge($branch->keys())->unique()->sort()->values();

        return $keys->map(function (string $key) use ($globals, $branch): array {
            /** @var Setting|null $branchRow */
            $branchRow = $branch->get($key);
            /** @var Setting|null $globalRow */
            $globalRow = $globals->get($key);
            $row = $branchRow ?? $globalRow;

            return [
                'key' => $key,
                'value' => $row->value,
                'type' => $row->type,
                'source' => $branchRow !== null ? 'branch' : 'global',
                'overridable' => true,
            ];
        })->all();
    }

    /**
     * Partial bulk upsert against $branchId. $entries is a map of
     * key => scalar|array, or key => ['value' => ..., 'type' => ...].
     * Keys not present are left untouched; a null value deletes the
     * branch-level override (falling back to the global, if any).
     *
     * @param  array<string, mixed>  $entries
     * @return array<int, array{key: string, value: mixed, type: string, source: string, overridable: bool}>
     */
    public function apply(int $branchId, array $entries): array
    {
        DB::transaction(function () use ($branchId, $entries): void {
            foreach ($entries as $key => $raw) {
                if (is_array($raw) && array_key_exists('value', $raw)) {
                    $value = $raw['value'];
                    $type = $raw['type'] ?? Setting::inferType($value);
                } else {
                    $value = $raw;
                    $type = Setting::inferType($raw);
                }

                if ($value === null) {
                    $this->settings->forget($branchId, $key);

                    continue;
                }

                $this->settings->put($branchId, $key, $value, $type);
            }
        });

        return $this->resolved($branchId);
    }
}
