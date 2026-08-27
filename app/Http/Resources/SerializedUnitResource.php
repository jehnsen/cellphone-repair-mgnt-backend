<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SerializedUnit */
class SerializedUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'imei' => $this->imei,
            'serial_number' => $this->serial_number,
            'condition' => $this->condition,
            'grade' => $this->grade,
            // Same gate as ProductResource::cost / TicketLineResource::unit_cost.
            'acquisition_cost' => $this->when($request->user()?->can('reports.margin.view'), $this->acquisition_cost),
            'acquisition_source' => $this->acquisition_source,
            'status' => $this->status,
            'warranty_terms' => $this->warranty_terms,
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
