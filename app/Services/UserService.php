<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function list(): LengthAwarePaginator
    {
        return $this->users->paginate();
    }

    public function create(array $data): User
    {
        $role = $data['role'];
        unset($data['role']);

        $user = $this->users->create($data);
        $user->assignRole($role);

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        $wasActive = (bool) $user->is_active;

        $user = $this->users->update($user, $data);

        if ($role !== null) {
            $user->syncRoles([$role]);
        }

        // Deactivating an employee purges their issued tokens. The Sanctum
        // callback in AppServiceProvider already refuses them, so this is
        // hygiene rather than the control itself — a revoked credential
        // shouldn't sit in personal_access_tokens waiting for someone to
        // flip is_active back on and silently re-arm every device the
        // employee ever logged in from.
        if ($wasActive && ! $user->is_active) {
            $user->tokens()->delete();
        }

        return $user;
    }

    public function delete(User $user): bool
    {
        // Same reasoning as deactivation above: a soft-deleted user must not
        // keep working credentials.
        $user->tokens()->delete();

        return $this->users->delete($user);
    }
}
