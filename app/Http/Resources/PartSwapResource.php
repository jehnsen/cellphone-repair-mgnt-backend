<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PartSwap
 *
 * `removed_photo_url` is a virtual attribute the controller sets before
 * wrapping (same pattern as TicketPhotoResource::url) — resolving it to a
 * signed URL belongs to a Service, not this Resource.
 */
class PartSwapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'removed_description' => $this->removed_description,
            'removed_serial' => $this->removed_serial,
            'removed_photo_url' => $this->removed_photo_url,
            'installed_product' => new ProductResource($this->whenLoaded('installedProduct')),
            'installed_serial' => $this->installed_serial,
            'disposition' => $this->disposition,
            'technician' => new UserResource($this->whenLoaded('technician')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
