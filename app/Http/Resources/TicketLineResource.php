<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TicketLine */
class TicketLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'line_type' => $this->line_type,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_cost' => $this->when($request->user()?->can('reports.margin.view'), $this->unit_cost),
            'unit_price' => $this->unit_price,
            'amount' => $this->amount,
            'stock_consumed' => $this->stock_movement_id !== null,
            'product' => new ProductResource($this->whenLoaded('product')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
