<?php

namespace App\Http\Requests\Api\V1\GoodsReceipt;

use App\Models\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Ad-hoc receiving — no purchase order backs this (see GoodsReceiptService's docblock). */
class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', GoodsReceipt::class);
    }

    public function rules(): array
    {
        return [
            'branch_ulid' => ['required', 'string', Rule::exists('branches', 'ulid')],
            'supplier_ulid' => ['required', 'string', Rule::exists('suppliers', 'ulid')],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_ulid' => ['required', 'string', Rule::exists('products', 'ulid')],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }
}
