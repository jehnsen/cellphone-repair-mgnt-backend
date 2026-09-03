<?php

namespace App\Http\Requests\Api\V1\SalesWarranty;

use App\Models\SerializedUnit;
use App\Models\SupplierReturn;
use App\Rules\ValidImei;
use App\Support\Imei;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CloseSupplierReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('close', $this->route('supplierReturn'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('replacement.imei')) {
            $replacement = $this->input('replacement');
            $replacement['imei'] = Imei::normalize((string) $replacement['imei']);
            $this->merge(['replacement' => $replacement]);
        }
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(SupplierReturn::OUTCOMES)],
            'outcome_notes' => ['nullable', 'string', 'max:2000'],

            'replacement' => ['required_if:outcome,replaced', 'prohibited_unless:outcome,replaced', 'array'],
            'replacement.imei' => ['exclude_unless:outcome,replaced', 'nullable', 'required_without:replacement.serial_number', new ValidImei],
            'replacement.serial_number' => ['exclude_unless:outcome,replaced', 'nullable', 'required_without:replacement.imei', 'string', 'max:60'],
            'replacement.condition' => ['exclude_unless:outcome,replaced', 'nullable', Rule::in(SerializedUnit::CONDITIONS)],
            'replacement.acquisition_cost' => ['exclude_unless:outcome,replaced', 'nullable', 'numeric', 'min:0'],

            'credit_amount' => ['required_if:outcome,credited', 'prohibited_unless:outcome,credited', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $imei = $this->input('replacement.imei');
            if (is_string($imei) && $imei !== '' && SerializedUnit::withoutGlobalScopes()->where('imei', $imei)->exists()) {
                $validator->errors()->add('replacement.imei', 'A serialized unit with this IMEI already exists.');
            }

            $serial = $this->input('replacement.serial_number');
            if (is_string($serial) && $serial !== '' && SerializedUnit::withoutGlobalScopes()->where('serial_number', $serial)->exists()) {
                $validator->errors()->add('replacement.serial_number', 'A serialized unit with this serial number already exists.');
            }
        });
    }
}
