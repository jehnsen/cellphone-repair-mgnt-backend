<?php

namespace App\Http\Requests\Api\V1\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards POST /api/v1/system/fresh-install. Two locks:
 *
 *  - authorize(): the caller must be an `owner` AND hold `users.manage`.
 *    This wipes every branch, user, sale, and ticket — it's not a
 *    manager-level action even though managers can manage most things.
 *  - rules(): the body must carry `confirm: "RESET"` verbatim, so the call
 *    can't be made by accident or by replaying an unrelated POST.
 */
class FreshInstallRequest extends FormRequest
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
            'confirm' => ['required', 'string', Rule::in(['RESET'])],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.in' => 'To confirm this irreversible reset, send "confirm": "RESET" in the request body.',
        ];
    }
}
