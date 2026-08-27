<?php

namespace App\Http\Requests\Api\V1\SerializedUnit;

use App\Models\Product;
use App\Models\SerializedUnit;
use App\Rules\ValidImei;
use App\Support\Imei;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSerializedUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SerializedUnit::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('imei')) {
            $this->merge(['imei' => Imei::normalize($this->string('imei'))]);
        }
    }

    public function rules(): array
    {
        return [
            'product_ulid' => ['required', 'string', Rule::exists('products', 'ulid')],
            'branch_ulid' => ['required', 'string', Rule::exists('branches', 'ulid')],
            'imei' => ['nullable', 'required_without:serial_number', new ValidImei],
            'serial_number' => ['nullable', 'required_without:imei', 'string', 'max:60'],
            'condition' => ['required', Rule::in(SerializedUnit::CONDITIONS)],
            'grade' => ['nullable', 'string', 'size:1'],
            'acquisition_cost' => ['required', 'numeric', 'min:0'],
            'acquisition_source' => ['nullable', 'string', 'max:60'],
            'warranty_terms' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ulid = $this->input('product_ulid');
            if (! is_string($ulid)) {
                return;
            }

            $product = Product::where('ulid', $ulid)->first();
            if ($product !== null && ! $product->is_serialized) {
                $validator->errors()->add('product_ulid', 'This product is not serialized — it tracks stock by quantity, not by individual unit.');
            }
        });
    }
}
