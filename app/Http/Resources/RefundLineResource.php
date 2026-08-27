<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RefundLine */
class RefundLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sale_line' => new SaleLineResource($this->whenLoaded('saleLine')),
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'restock_behavior' => $this->restock_behavior,
        ];
    }
}
