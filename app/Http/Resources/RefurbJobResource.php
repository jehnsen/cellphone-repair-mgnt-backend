<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RefurbJob */
class RefurbJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'status' => $this->status,
            'labor_cost' => $this->when($request->user()?->can('reports.margin.view'), $this->labor_cost),
            'parts_cost' => $this->when($request->user()?->can('reports.margin.view'), $this->parts_cost),
            'landed_cost' => $this->when($request->user()?->can('reports.margin.view'), $this->landed_cost),
            'serialized_unit' => new SerializedUnitResource($this->whenLoaded('serializedUnit')),
            'lines' => RefurbJobLineResource::collection($this->whenLoaded('lines')),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
