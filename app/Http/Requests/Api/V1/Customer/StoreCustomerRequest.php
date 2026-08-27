<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Branch;
use App\Models\Customer;
use App\Rules\PhMobile;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('mobile')) {
            $this->merge(['mobile' => PhoneNumber::normalize($this->string('mobile'))]);
        }
    }

    public function rules(): array
    {
        // Resolved here (not in the controller) so the unique rule below can
        // scope the duplicate check to the right branch. A bad/blank ULID
        // just yields null — the `exists` rule reports that separately.
        $branchId = $this->filled('branch_ulid')
            ? Branch::query()->where('ulid', $this->string('branch_ulid'))->value('id')
            : null;

        return [
            'branch_ulid' => ['required', 'string', Rule::exists('branches', 'ulid')],
            'name' => ['required', 'string', 'max:255'],
            'mobile' => [
                'nullable',
                new PhMobile,
                Rule::unique('customers', 'mobile')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_blacklisted' => ['boolean'],
            'blacklist_reason' => ['nullable', 'string', 'required_if:is_blacklisted,true'],
        ];
    }
}
