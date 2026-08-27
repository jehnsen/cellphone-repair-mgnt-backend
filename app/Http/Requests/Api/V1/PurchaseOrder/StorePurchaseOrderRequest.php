<?php

namespace App\Http\Requests\Api\V1\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PurchaseOrder::class);
    }

    public function rules(): array
    {
        return [
            'branch_ulid' => ['required', 'string', Rule::exists('branches', 'ulid')],
            'supplier_ulid' => ['required', 'string', Rule::exists('suppliers', 'ulid')],
            'expected_date' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_ulid' => ['required', 'string', Rule::exists('products', 'ulid')],
            'lines.*.ordered_qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }
}
