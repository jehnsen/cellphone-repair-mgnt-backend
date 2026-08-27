<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StockAdjustmentLine */
class StockAdjustmentLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'quantity_delta' => $this->quantity_delta,
            'unit_cost' => $this->when($request->user()?->can('reports.margin.view'), $this->unit_cost),
            'product' => new ProductResource($this->whenLoaded('product')),
            'serialized_unit' => new SerializedUnitResource($this->whenLoaded('serializedUnit')),
        ];
    }
}
