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

        $user = $this->users->update($user, $data);

        if ($role !== null) {
            $user->syncRoles([$role]);
        }

        return $user;
    }

    public function delete(User $user): bool
    {
        return $this->users->delete($user);
    }
}
