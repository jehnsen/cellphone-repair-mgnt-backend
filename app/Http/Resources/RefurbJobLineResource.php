<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RefurbJobLine */
class RefurbJobLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product' => new ProductResource($this->whenLoaded('product')),
            'quantity' => $this->quantity,
            'unit_cost' => $this->when($request->user()?->can('reports.margin.view'), $this->unit_cost),
        ];
    }
}
