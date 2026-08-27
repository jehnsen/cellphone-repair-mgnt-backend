<?php

namespace App\Http\Requests\Api\V1\Sale;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A line is identified by its position in `sale.lines` (as returned by
 * GET /sales/{sale}) — sale_lines has no ulid of its own, so there's
 * nothing else to reference one by without exposing an internal id.
 */
class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('refund', Sale::class);
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', 'max:40'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_index' => ['required', 'integer', 'min:0'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.restock_behavior' => ['required', Rule::in(['restock', 'no_restock', 'write_off'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sale = $this->route('sale');
            $lineCount = $sale->lines()->count();

            foreach ($this->input('lines', []) as $i => $line) {
                $index = $line['line_index'] ?? null;
                if (is_int($index) && $index >= $lineCount) {
                    $validator->errors()->add("lines.{$i}.line_index", "This sale only has {$lineCount} line(s).");
                }
            }
        });
    }
}
