<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\StockMovement
 *
 * `reference_id` is an internal BIGINT (Rule 6: never expose sequential
 * ids) and there's no morphMap yet to resolve it to the referenced row's
 * own ulid, so only the human-readable `reference_type` label ships for
 * now — e.g. "stock_adjustment", "goods_receipt".
 */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'product' => new ProductResource($this->whenLoaded('product')),
            'serialized_unit' => new SerializedUnitResource($this->whenLoaded('serializedUnit')),
            'quantity' => $this->quantity,
            'unit_cost' => $this->when($request->user()?->can('reports.margin.view'), $this->unit_cost),
            'movement_type' => $this->movement_type,
            'reference_type' => $this->reference_type,
            'reason_code' => $this->reason_code,
            'balance_after' => $this->balance_after,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
