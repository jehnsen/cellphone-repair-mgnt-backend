<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TicketEvent */
class TicketEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'event_type' => $this->event_type,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'note' => $this->note,
            'metadata' => $this->metadata,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
