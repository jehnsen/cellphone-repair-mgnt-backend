<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Sale */
class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'sale_number' => $this->sale_number,
            'status' => $this->status,
            'source' => $this->source,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'vatable_sales' => $this->vatable_sales,
            'vat_exempt_sales' => $this->vat_exempt_sales,
            'zero_rated_sales' => $this->zero_rated_sales,
            'vat_amount' => $this->vat_amount,
            'total' => $this->total,
            'void_reason' => $this->void_reason,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'cashier' => new UserResource($this->whenLoaded('cashier')),
            'lines' => SaleLineResource::collection($this->whenLoaded('lines')),
            'discounts' => DiscountResource::collection($this->whenLoaded('discounts')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
