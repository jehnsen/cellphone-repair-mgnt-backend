<?php

namespace App\Http\Requests\Api\V1\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards POST /api/v1/system/clear-transactional-data. Same two-lock shape
 * as FreshInstallRequest, with its own confirmation phrase so muscle memory
 * from one destructive endpoint can't accidentally trigger the other:
 *
 *  - authorize(): the caller must be an `owner` AND hold `users.manage` —
 *    this empties every repair ticket, sale, and inventory movement across
 *    every branch, not something a manager-level role should trigger.
 *  - rules(): the body must carry `confirm: "CLEAR_SAMPLE_DATA"` verbatim.
 */
class ClearTransactionalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasRole('owner')
            && $user->can('users.manage');
    }

    public function rules(): array
    {
        return [
            'confirm' => ['required', 'string', Rule::in(['CLEAR_SAMPLE_DATA'])],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.in' => 'To confirm clearing transactional data, send "confirm": "CLEAR_SAMPLE_DATA" in the request body.',
        ];
    }
}
