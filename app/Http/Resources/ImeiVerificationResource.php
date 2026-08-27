<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ImeiVerification */
class ImeiVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'phase' => $this->phase,
            'scanned_imei' => $this->scanned_imei,
            'matches_expected' => $this->matches_expected,
            'override_reason' => $this->override_reason,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'overridden_by' => new UserResource($this->whenLoaded('overriddenBy')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
