<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'branch_ulid' => ['sometimes', 'string', Rule::exists('branches', 'ulid')],
            'employee_code' => ['sometimes', 'string', 'max:20', Rule::unique('users', 'employee_code')->ignore($target->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(['owner', 'manager', 'cashier', 'technician'])],
            'is_active' => ['boolean'],
        ];
    }
}
