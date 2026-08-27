<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use App\Support\RepairFinding\Defect;
use App\Support\RepairFinding\Resolution;
use App\Support\RepairFinding\RootCause;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertRepairFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordFinding', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'min:3', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
            'root_cause' => ['required', Rule::in(RootCause::values())],
            'defects' => ['nullable', 'array'],
            'defects.*' => ['distinct', Rule::in(Defect::values())],
            'resolution' => ['required', Rule::in(Resolution::values())],
            'technician_notes' => ['nullable', 'string', 'max:5000'],
            'qc_passed' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (filled($this->input('details'))) {
                return;
            }

            // `details` becomes mandatory when the controlled vocabulary
            // can't carry the explanation on its own — an "other" root
            // cause, or an "unrepairable" verdict the customer is told
            // directly.
            if ($this->input('root_cause') === RootCause::Other->value) {
                $validator->errors()->add('details', 'The details field is required when the root cause is "other".');
            } elseif ($this->input('resolution') === Resolution::Unrepairable->value) {
                $validator->errors()->add('details', 'The details field is required when the resolution is "unrepairable".');
            }
        });
    }
}
