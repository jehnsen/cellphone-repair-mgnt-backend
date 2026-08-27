<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InstallmentSchedule */
class InstallmentScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'due_date' => $this->due_date?->toDateString(),
            'amount_due' => $this->amount_due,
            'amount_paid' => $this->amount_paid,
            'status' => $this->status,
        ];
    }
}
