<?php

namespace App\Http\Requests\Api\V1\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Lines are keyed by product_ulid, not an internal purchase_order_line id —
 * purchase_order_lines has no ulid of its own (nested-only, per the design
 * doc), and Rule 6 (never expose a sequential id, including in request
 * bodies) rules out sending that id back to the client just so it can
 * round-trip it here.
 */
class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('purchaseOrder'));
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_ulid' => ['required', 'string', Rule::exists('products', 'ulid')],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $po = $this->route('purchaseOrder')->load('lines.product');

            foreach ($this->input('lines', []) as $i => $line) {
                $ulid = $line['product_ulid'] ?? null;
                if (! is_string($ulid)) {
                    continue;
                }

                if (! $po->lines->contains(fn ($l) => $l->product?->ulid === $ulid)) {
                    $validator->errors()->add("lines.{$i}.product_ulid", 'This product is not on this purchase order.');
                }
            }
        });
    }
}
