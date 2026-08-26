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

            'unlock_method' => ['nullable', Rule::in(['pin', 'pattern', 'password', 'none'])],
            'unlock_value' => ['nullable', 'string', 'required_unless:unlock_method,none'],

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
