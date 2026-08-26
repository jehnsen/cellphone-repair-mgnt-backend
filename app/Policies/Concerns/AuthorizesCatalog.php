<?php

namespace App\Policies\Concerns;

use App\Models\User;

/** Shared by every catalog policy (brands, models, services, categories, products). */
trait AuthorizesCatalog
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.view');
    }

    public function view(User $user): bool
    {
        return $user->can('catalog.view');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('catalog.manage');
    }
}
