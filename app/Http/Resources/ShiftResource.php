<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Shift */
class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'cashier' => new UserResource($this->whenLoaded('cashier')),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'opening_float' => $this->opening_float,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'counted_cash' => $this->counted_cash,
            'expected_cash' => $this->expected_cash,
            'variance' => $this->variance,
            'notes' => $this->notes,
            'is_open' => $this->isOpen(),
        ];
    }
}
