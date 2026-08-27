<?php

namespace App\Http\Requests\Api\V1\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('purchase_order'));
    }

    public function rules(): array
    {
        return [
            'expected_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(array_diff(PurchaseOrder::STATUSES, ['partially_received', 'received']))],
        ];
    }
}
