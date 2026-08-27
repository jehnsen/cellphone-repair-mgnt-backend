<?php

namespace App\Http\Resources;

use App\Models\RepairTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RepairTicket */
class RepairTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'ticket_number' => $this->ticket_number,
            'claim_code' => $this->claim_code,
            'status' => $this->status,
            'device' => [
                'brand' => $this->device_brand_snapshot,
                'model' => $this->device_model_snapshot,
                'color' => $this->device_color_snapshot,
            ],
            'reported_problem' => $this->reported_problem,
            'problem_tags' => $this->problem_tags,
            // Encrypted at rest; only shown to staff who can actually work
            // the ticket (a technician needs it to unlock the device).
            'unlock_method' => $this->when($request->user()?->can('tickets.update'), $this->unlock_method),
            'unlock_value' => $this->when($request->user()?->can('tickets.update'), $this->unlock_value),
            'accessories_turned_over' => $this->accessories_turned_over,
            'intake_condition_checklist' => $this->intake_condition_checklist,
            'estimated_cost' => $this->estimated_cost,
            'approved_amount' => $this->approved_amount,
            'downpayment' => $this->downpayment,
            'balance' => $this->balance,
            // Cost/margin is permission-gated the same way ProductResource
            // gates `cost` (module 3: "Show margin per ticket to
            // owner/admin roles only"). Only computable once lines are
            // eager-loaded.
            'margin' => $this->when(
                $request->user()?->can('reports.margin.view') && $this->relationLoaded('lines'),
                fn () => round((float) $this->approved_amount - $this->lines->sum(fn ($line) => (float) ($line->unit_cost ?? 0) * (float) $line->quantity), 2),
            ),
            'promised_date' => $this->promised_date?->toDateString(),
            'warranty_days_offered' => $this->warranty_days_offered,
            'terms_accepted' => $this->terms_accepted,
            'terms_accepted_at' => $this->terms_accepted_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'customer_device' => new CustomerDeviceResource($this->whenLoaded('customerDevice')),
            'assigned_technician' => new UserResource($this->whenLoaded('assignedTechnician')),
            // Compact summary for the detail header only — `show` loads
            // this, the board's `index` deliberately does not. The full
            // record is GET /tickets/{ulid}/finding. `null` until recorded.
            'finding' => $this->when(
                $this->relationLoaded('finding'),
                fn () => $this->finding === null ? null : [
                    'summary' => $this->finding->summary,
                    'root_cause' => $this->finding->root_cause,
                    'resolution' => $this->finding->resolution,
                    'qc_passed' => $this->finding->qc_passed,
                ],
            ),
            // Backs GET /public/verify/{token} (chain-of-custody proof) —
            // staff embed this in the printed claim stub/warranty slip as a
            // QR code. Not a secret the way claim_code is, but still staff-only.
            'verification_token' => $this->when(
                $request->user()?->can('tickets.view') && $this->relationLoaded('verificationToken'),
                fn () => $this->verificationToken?->token,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
