<?php

namespace App\Http\Requests\Api\V1\Acquisition;

use App\Models\Acquisition;
use App\Rules\ValidImei;
use App\Support\Imei;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcquisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Acquisition::class);
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
            'branch_ulid' => ['required', 'string', Rule::exists('branches', 'ulid')],
            'seller_name' => ['required', 'string', 'max:120'],
            'seller_id_type' => ['required', 'string', 'max:30'],
            'seller_id_number' => ['required', 'string', 'max:60'],
            'seller_id_photo_ref' => ['nullable', 'string', 'max:255'],
            'declared_source' => ['nullable', 'string'],
            'offered_price' => ['required', 'numeric', 'min:0'],
            'imei' => ['required', new ValidImei],
            'condition_assessment' => ['nullable', 'string'],
            'purchase_date' => ['required', 'date'],
        ];
    }
}
