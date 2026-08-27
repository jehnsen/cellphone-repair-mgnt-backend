<?php

namespace App\Http\Requests\Api\V1\SerializedUnit;

use App\Models\SerializedUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSerializedUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('serialized_unit'));
    }

    public function rules(): array
    {
        return [
            'condition' => ['sometimes', Rule::in(SerializedUnit::CONDITIONS)],
            'grade' => ['nullable', 'string', 'size:1'],
            'acquisition_cost' => ['sometimes', 'numeric', 'min:0'],
            'acquisition_source' => ['nullable', 'string', 'max:60'],
            'warranty_terms' => ['nullable', 'string'],
            // 'sold' is deliberately excluded — that status change belongs
            // to the sales flow (Stage 8), which records its own
            // sale-referenced movement instead of this generic PATCH.
            'status' => ['sometimes', Rule::in(array_diff(SerializedUnit::STATUSES, ['sold']))],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Marking a unit sold happens through the sales flow, not this endpoint.',
        ];
    }
}
