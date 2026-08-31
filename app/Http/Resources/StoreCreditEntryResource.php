<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StoreCreditEntry */
class StoreCreditEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'direction' => $this->direction,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'reason' => $this->reason,
            'reference_type' => $this->reference_type,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
