<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordPayment', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(Payment::METHODS)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:60', 'required_if:method,gcash,maya,card,bank_transfer'],
            'tendered' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
