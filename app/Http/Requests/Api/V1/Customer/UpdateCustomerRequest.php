<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Branch;
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
        $customer = $this->route('customer');

        // Prefer an explicitly-supplied branch, falling back to the
        // customer's current one, so the duplicate check stays branch-scoped
        // even when the request only touches `mobile`.
        $branchId = $this->filled('branch_ulid')
            ? Branch::query()->where('ulid', $this->string('branch_ulid'))->value('id')
            : $customer?->branch_id;

        return [
            'branch_ulid' => ['sometimes', 'string', Rule::exists('branches', 'ulid')],
            'name' => ['sometimes', 'string', 'max:255'],
            'mobile' => [
                'sometimes',
                'nullable',
                new PhMobile,
                Rule::unique('customers', 'mobile')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
                    ->ignore($customer?->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_blacklisted' => ['boolean'],
            'blacklist_reason' => ['nullable', 'string', 'required_if:is_blacklisted,true'],
        ];
    }
}
