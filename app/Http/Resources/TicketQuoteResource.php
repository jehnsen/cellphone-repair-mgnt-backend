<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TicketQuote */
class TicketQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'quoted_amount' => $this->quoted_amount,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'channel' => $this->channel,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'decision' => $this->decision,
            'responder_note' => $this->responder_note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
