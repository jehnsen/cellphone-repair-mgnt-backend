<?php

namespace App\Http\Requests\Api\V1\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('create', User::class)) {
            return false;
        }

        // An account can never be granted a role its creator couldn't
        // hold. Checked here rather than as a validation rule so it comes
        // back 403, not 422 — it's an authorization failure, not bad input.
        // Non-scalar input is normalized to null; the `role` rules below
        // are what reject it, with a 422.
        $role = $this->input('role');

        return $this->user()->can('assignRole', [User::class, is_string($role) ? $role : null]);
    }

    public function rules(): array
    {
        return [
            // Clients never see or send sequential ids (Rule 6) — the
            // controller resolves this ULID to an internal branch_id.
            'branch_ulid' => ['required', 'string', Rule::exists('branches', 'ulid')],
            'employee_code' => ['required', 'string', 'max:20', Rule::unique('users', 'employee_code')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8)],
            'role' => ['required', Rule::in(['owner', 'manager', 'cashier', 'technician'])],
            'is_active' => ['boolean'],
        ];
    }
}
