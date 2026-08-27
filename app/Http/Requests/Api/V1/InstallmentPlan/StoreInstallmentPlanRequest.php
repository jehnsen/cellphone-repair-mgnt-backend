<?php

namespace App\Http\Requests\Api\V1\InstallmentPlan;

use App\Models\InstallmentPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstallmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', InstallmentPlan::class);
    }

    public function rules(): array
    {
        return [
            'sale_ulid' => ['required', 'string', Rule::exists('sales', 'ulid')],
            'downpayment' => ['nullable', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1', 'max:36'],
        ];
    }
}
