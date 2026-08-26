<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondTicketQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'declined', 'partial', 'no_response'])],
            'responder_note' => ['nullable', 'string'],
        ];
    }
}
