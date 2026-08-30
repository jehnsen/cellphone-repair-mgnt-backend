<?php

namespace App\Http\Requests\Api\V1\Setting;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Partial bulk patch: `settings` is a flat map of key => value (or
 * key => {value, type}). Keys are open-ended — the generic settings table
 * deliberately doesn't enumerate them (receipt header/footer, TIN, VAT
 * registration etc. live as real columns on `branches`; this holds the
 * rest: BIR display toggle, feature flags, notification prefs).
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * An entry is *either* a literal value (scalar, or a JSON array/object)
     * *or* the tagged form `{"value": ..., "type": "..."}` — recognised only
     * when the array carries a `value` key. `type`, when present in the
     * tagged form, must be one of Setting::TYPES.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('settings', []) as $key => $entry) {
                if (! is_string($key) || $key === '' || mb_strlen($key) > 100) {
                    $validator->errors()->add("settings.{$key}", 'Setting keys must be non-empty strings of at most 100 characters.');

                    continue;
                }

                $isTagged = is_array($entry) && array_key_exists('value', $entry);

                if ($isTagged && isset($entry['type']) && ! in_array($entry['type'], Setting::TYPES, true)) {
                    $validator->errors()->add("settings.{$key}.type", 'The setting type is invalid.');
                }
            }
        });
    }
}
