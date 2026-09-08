<?php

namespace App\Http\Requests\Api\V1\DevicePart;

use App\Models\DevicePart;
use App\Support\Diagnosis\IssueSource;
use App\Support\Diagnosis\ProblemTag;
use App\Support\RepairFinding\Defect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Replaces the part list for one issue key. The issue key is validated
 * against whichever vocabulary the source names — the two overlap ('screen'
 * and 'battery' are in both), so neither list alone would catch a key sent
 * under the wrong source.
 */
class UpdateIssuePartMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DevicePart::class);
    }

    public function rules(): array
    {
        return [
            'issue_source' => ['required', Rule::in(IssueSource::values())],
            'issue_key' => ['required', 'string', 'max:48'],
            'parts' => ['present', 'array', 'max:12'],
            'parts.*.part_ulid' => ['required', 'string', Rule::exists('device_parts', 'ulid')],
            'parts.*.rank' => ['nullable', 'integer', 'min:1', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $source = IssueSource::tryFrom((string) $this->input('issue_source'));

            if ($source === null) {
                return;
            }

            $allowed = match ($source) {
                IssueSource::ProblemTag => ProblemTag::values(),
                IssueSource::Defect => Defect::values(),
            };

            if (! in_array($this->input('issue_key'), $allowed, true)) {
                $validator->errors()->add(
                    'issue_key',
                    'The selected issue key is not part of the '.$source->value.' vocabulary.',
                );
            }

            // A part listed twice would make the highlight order ambiguous and
            // silently violate the table's unique constraint at write time.
            $ulids = array_column((array) $this->input('parts', []), 'part_ulid');

            if (count($ulids) !== count(array_unique($ulids))) {
                $validator->errors()->add('parts', 'The same part cannot be listed twice for one issue.');
            }
        });
    }
}
