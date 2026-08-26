<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'quoted_amount' => ['required', 'numeric', 'min:0'],
            'channel' => ['required', Rule::in(['call', 'sms', 'viber', 'email', 'in_person', 'app'])],
        ];
    }
}
