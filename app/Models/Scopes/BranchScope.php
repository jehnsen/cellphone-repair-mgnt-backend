<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * No multi-tenancy beyond the branch column exists yet, but every
 * branch-scoped query goes through this global scope so multi-tenancy is a
 * small change later (see docs/design/01-domain-design.md — "Out of scope").
 *
 * Scopes to the authenticated user's branch when one is resolvable; a no-op
 * otherwise, so console commands, seeders, and system/cross-branch jobs see
 * everything.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $branchId = Auth::check() ? Auth::user()?->branch_id : null;

        if ($branchId !== null) {
            $builder->where($model->qualifyColumn('branch_id'), $branchId);
        }
    }
}
