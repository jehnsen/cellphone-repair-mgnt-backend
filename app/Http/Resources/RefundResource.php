<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Refund */
class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'reason_code' => $this->reason_code,
            'refund_method' => $this->refund_method,
            'total_amount' => $this->total_amount,
            'processor' => new UserResource($this->whenLoaded('processor')),
            'lines' => RefundLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
