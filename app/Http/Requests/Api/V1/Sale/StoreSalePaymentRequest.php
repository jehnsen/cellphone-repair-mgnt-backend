<?php

namespace App\Http\Requests\Api\V1\Sale;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Sale::class);
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(Payment::METHODS)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:60', 'required_if:method,gcash,maya,card,bank_transfer'],
            'tendered' => ['nullable', 'numeric', 'min:0'],
            // method=trade_in: the completed buy-back whose offered_price is
            // the credit being applied (see PaymentRecorder).
            'acquisition_ulid' => ['nullable', 'string', 'size:26', 'required_if:method,trade_in'],
        ];
    }
}
