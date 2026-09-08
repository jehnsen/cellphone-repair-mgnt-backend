<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use App\Support\Diagnosis\IssueSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Multipart, not JSON: the rendered canvas arrives as a PNG file alongside the
 * selection state it was drawn from, the same shape as a ticket photo upload.
 */
class StoreTicketDiagnosisSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Capturing what the customer was shown is part of recording the
        // conclusion, so it rides on the same gate as the finding itself.
        return $this->user()->can('recordFinding', $this->route('ticket'));
    }

    protected function prepareForValidation(): void
    {
        // Multipart carries everything as strings, so the two JSON-shaped
        // fields arrive encoded. Decode before the array rules run.
        foreach (['issue_keys', 'part_keys', 'camera'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192'],

            'issue_source' => ['required', Rule::in(IssueSource::values())],
            'issue_keys' => ['present', 'array', 'max:20'],
            'issue_keys.*' => ['string', 'max:48', 'distinct'],

            // The parts actually highlighted, resolved client-side at capture
            // time. Stored as given rather than re-derived, because the point
            // of a snapshot is that it does not move when the mapping does.
            'part_keys' => ['present', 'array', 'max:40'],
            'part_keys.*' => ['string', 'max:48', 'distinct', Rule::exists('device_parts', 'key')],

            'camera' => ['nullable', 'array'],
            'camera.x' => ['required_with:camera', 'numeric'],
            'camera.y' => ['required_with:camera', 'numeric'],
            'camera.z' => ['required_with:camera', 'numeric'],
            'camera.target' => ['nullable', 'array'],
            'camera.target.x' => ['required_with:camera.target', 'numeric'],
            'camera.target.y' => ['required_with:camera.target', 'numeric'],
            'camera.target.z' => ['required_with:camera.target', 'numeric'],

            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
