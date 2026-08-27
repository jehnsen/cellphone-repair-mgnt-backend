<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StockLevel */
class StockLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product' => new ProductResource($this->whenLoaded('product')),
            'on_hand_qty' => $this->on_hand_qty,
            'reserved_qty' => $this->reserved_qty,
            'available_qty' => round((float) $this->on_hand_qty - (float) $this->reserved_qty, 2),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
