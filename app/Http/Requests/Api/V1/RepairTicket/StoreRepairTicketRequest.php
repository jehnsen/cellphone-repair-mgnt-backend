<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use App\Models\RepairTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepairTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RepairTicket::class);
    }

    protected function prepareForValidation(): void
    {
        // A method with no accompanying secret is the same as no unlock
        // info at all — the intake form defaults the method dropdown to
        // 'pin' even when the tech leaves the value blank. Normalize that
        // to 'none' so it doesn't trip the required_if rule below.
        if ($this->has('unlock_method') && ! $this->filled('unlock_value')) {
            $this->merge(['unlock_method' => 'none', 'unlock_value' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'branch_ulid' => ['required', 'string', Rule::exists('branches', 'ulid')],
            'customer_ulid' => ['required', 'string', Rule::exists('customers', 'ulid')],
            'customer_device_ulid' => ['required', 'string', Rule::exists('customer_devices', 'ulid')],
            'assigned_technician_ulid' => ['nullable', 'string', Rule::exists('users', 'ulid')],

            'reported_problem' => ['nullable', 'string'],
            'problem_tags' => ['nullable', 'array'],
            'problem_tags.*' => ['string', Rule::in([
                'screen', 'battery', 'charging_port', 'water_damage', 'no_power',
                'software', 'camera', 'speaker', 'board_level',
            ])],

            // Both optional: a customer can decline to hand over an unlock
            // secret at intake. If they do give a method other than 'none',
            // the value is still expected alongside it.
            'unlock_method' => ['nullable', Rule::in(['pin', 'pattern', 'password', 'none'])],
            'unlock_value' => ['nullable', 'string', 'required_if:unlock_method,pin,pattern,password'],

            'accessories_turned_over' => ['nullable', 'array'],
            'accessories_turned_over.*' => ['string', Rule::in(['sim', 'sd_card', 'case', 'charger', 'box'])],

            'intake_condition_checklist' => ['nullable', 'array'],

            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'downpayment' => ['nullable', 'numeric', 'min:0'],
            'promised_date' => ['nullable', 'date'],
            'warranty_days_offered' => ['nullable', 'integer', 'min:0'],

            'terms_accepted' => ['required', 'accepted'],
        ];
    }
}
