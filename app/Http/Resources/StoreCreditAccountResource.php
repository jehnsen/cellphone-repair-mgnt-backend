<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StoreCreditAccount */
class StoreCreditAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'balance' => $this->balance,
            'entries' => StoreCreditEntryResource::collection($this->whenLoaded('entries')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
