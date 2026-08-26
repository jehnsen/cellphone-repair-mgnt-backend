<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TicketPhoto
 *
 * `signed_url` is a virtual attribute the controller sets before wrapping —
 * generating it belongs to TicketPhotoService, not this Resource (Rule
 * Zero: binary never travels through a controller, but the short-TTL
 * signed URL that stands in for it is exactly what this returns).
 */
class TicketPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'phase' => $this->phase,
            'sha256_hash' => $this->sha256_hash,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'captured_by' => new UserResource($this->whenLoaded('capturedBy')),
            'url' => $this->signed_url,
        ];
    }
}
