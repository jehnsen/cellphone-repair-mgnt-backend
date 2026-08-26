<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Rules\PhMobile;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('mobile')) {
            $this->merge(['mobile' => PhoneNumber::normalize($this->string('mobile'))]);
        }
    }

    public function rules(): array
    {
        return [
            'branch_ulid' => ['sometimes', 'string', Rule::exists('branches', 'ulid')],
            'name' => ['sometimes', 'string', 'max:255'],
            'mobile' => ['sometimes', new PhMobile],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_blacklisted' => ['boolean'],
            'blacklist_reason' => ['nullable', 'string', 'required_if:is_blacklisted,true'],
        ];
    }
}
