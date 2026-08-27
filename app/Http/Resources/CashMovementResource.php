<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CashMovement */
class CashMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'direction' => $this->direction,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
