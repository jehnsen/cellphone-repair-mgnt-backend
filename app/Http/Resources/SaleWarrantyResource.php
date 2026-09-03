<?php

namespace App\Http\Resources;

use App\Models\SaleWarranty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaleWarranty */
class SaleWarrantyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'warranty_code' => $this->warranty_code,
            'coverage' => $this->coverage,
            'term_days' => $this->term_days,
            'starts_at' => $this->starts_at?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'is_active' => $this->isActive(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'terms' => $this->terms,
            'exclusions' => $this->exclusions,
            'sale_ulid' => $this->whenLoaded('sale', fn () => $this->sale?->ulid),
            'serialized_unit' => new SerializedUnitResource($this->whenLoaded('serializedUnit')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'claims' => SaleWarrantyClaimResource::collection($this->whenLoaded('claims')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
