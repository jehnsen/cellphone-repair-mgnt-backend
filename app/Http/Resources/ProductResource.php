<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'type' => $this->type,
            // Cost/margin is permission-gated in the resource layer, not
            // merely hidden by the client (docs/design/01-domain-design.md §2.1).
            'cost' => $this->when($request->user()?->can('reports.margin.view'), $this->cost),
            'selling_price' => $this->selling_price,
            'is_serialized' => $this->is_serialized,
            'reorder_point' => $this->reorder_point,
            'track_inventory' => $this->track_inventory,
            'is_active' => $this->is_active,
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'brand' => new DeviceBrandResource($this->whenLoaded('brand')),
            'compatible_device_models' => DeviceModelResource::collection($this->whenLoaded('compatibleDeviceModels')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
