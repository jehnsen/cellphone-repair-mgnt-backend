<?php

namespace App\Http\Requests\Api\V1\StockAdjustment;

use App\Models\StockAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockAdjustment::class);
    }

    public function rules(): array
    {
        return [
            'branch_ulid' => ['required', 'string', Rule::exists('branches', 'ulid')],
            'reason_code' => ['required', 'string', 'max:40'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_ulid' => ['required', 'string', Rule::exists('products', 'ulid')],
            'lines.*.serialized_unit_ulid' => ['nullable', 'string', Rule::exists('serialized_units', 'ulid')],
            'lines.*.quantity_delta' => ['required', 'numeric', 'not_in:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('lines', []) as $i => $line) {
                $hasUnit = ! empty($line['serialized_unit_ulid'] ?? null);
                $delta = (float) ($line['quantity_delta'] ?? 0);

                if ($hasUnit && abs($delta) !== 1.0) {
                    $validator->errors()->add(
                        "lines.{$i}.quantity_delta",
                        'A line adjusting a serialized unit must move exactly ±1 — a unit is either present or it isn\'t.',
                    );
                }
            }
        });
    }
}
