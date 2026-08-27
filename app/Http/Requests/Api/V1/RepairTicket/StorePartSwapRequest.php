<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use App\Models\TicketPhoto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePartSwapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordPartSwap', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'removed_description' => ['required', 'string', 'max:160'],
            'removed_serial' => ['nullable', 'string', 'max:60'],
            'removed_photo_ulid' => ['nullable', 'string', Rule::exists('ticket_photos', 'ulid')],
            'installed_product_ulid' => ['required', 'string', Rule::exists('products', 'ulid')],
            'installed_serial' => ['nullable', 'string', 'max:60'],
            'disposition' => ['required', Rule::in(['returned_to_customer', 'retained_for_disposal', 'returned_to_supplier'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ulid = $this->input('removed_photo_ulid');
            if (! is_string($ulid)) {
                return;
            }

            $photo = TicketPhoto::where('ulid', $ulid)->first();
            $ticket = $this->route('ticket');

            if ($photo !== null && $photo->repair_ticket_id !== $ticket->id) {
                $validator->errors()->add('removed_photo_ulid', 'This photo does not belong to this ticket.');
            }
        });
    }
}
