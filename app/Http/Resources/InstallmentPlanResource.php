<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InstallmentPlan */
class InstallmentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'principal' => $this->principal,
            'downpayment' => $this->downpayment,
            'term_months' => $this->term_months,
            'schedule_rule' => $this->schedule_rule,
            'status' => $this->status,
            'sale' => new SaleResource($this->whenLoaded('sale')),
            'schedules' => InstallmentScheduleResource::collection($this->whenLoaded('schedules')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
