<?php

namespace App\Http\Requests\Api\V1\Branch;

use App\Rules\PhMobile;
use App\Rules\ValidTin;
use App\Support\BranchType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('branch'));
    }

    public function rules(): array
    {
        $branch = $this->route('branch');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:10', Rule::unique('branches', 'code')->ignore($branch->id)],
            'type' => ['sometimes', Rule::enum(BranchType::class)],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'contact_phone' => ['nullable', new PhMobile],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'tin' => ['nullable', new ValidTin],
            'bir_permit_no' => ['nullable', 'string', 'max:255'],
            'vat_registered' => ['boolean'],
            'receipt_header_text' => ['nullable', 'string'],
            'receipt_footer_text' => ['nullable', 'string'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ];
    }
}
