<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\StoreCreditEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manual manager adjustment of a customer's store-credit balance — a
 * goodwill grant (`credit`) or a correction/clawback (`debit`). Refund- and
 * payment-driven movements go through RefundService / PaymentRecorder, not
 * this endpoint.
 */
class StoreCreditAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('store_credit.manage');
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(StoreCreditEntry::DIRECTIONS)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:60'],
        ];
    }
}
