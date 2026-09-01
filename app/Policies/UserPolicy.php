<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Roles that may only be granted by an owner. Creating staff is
     * `users.manage` (owner-only today), but that permission is a role
     * assignment away from a manager — and the moment it moves, nothing
     * else would stop the new holder from minting themselves an owner
     * account. Checked in assignRole() below, called from the Store/Update
     * user requests.
     */
    private const OWNER_ONLY_ROLES = ['owner'];

    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.manage');
    }

    /**
     * Whether $user may create or update an account carrying $role. An
     * account can never be granted a role its creator couldn't hold —
     * privilege escalation via the staff endpoint.
     */
    public function assignRole(User $user, ?string $role): bool
    {
        if (! $user->can('users.manage')) {
            return false;
        }

        // authorize() runs before validation, so `role` may still be
        // missing or non-scalar here. Let it through — the `required` /
        // `Rule::in` rules reject it a moment later with a proper 422,
        // which is the accurate answer for absent input.
        if ($role === null) {
            return true;
        }

        return ! in_array($role, self::OWNER_ONLY_ROLES, true) || $user->hasRole('owner');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.manage') && $user->isNot($target);
    }
}
