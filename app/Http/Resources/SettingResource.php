<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps one already-resolved entry from SettingService (a plain array, not
 * an Eloquent model) — `source` says whether the value is this branch's own
 * override or the shop-wide default.
 *
 * @property-read array{key: string, value: mixed, type: string, source: string, overridable: bool} $resource
 */
class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'value' => $this->resource['value'],
            'type' => $this->resource['type'],
            'source' => $this->resource['source'],
            'overridable' => $this->resource['overridable'],
        ];
    }
}
