<?php

namespace App\Models\Scopes;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * No multi-tenancy beyond the branch column exists yet, but every
 * branch-scoped query goes through this global scope so multi-tenancy is a
 * small change later (see docs/design/01-domain-design.md — "Out of scope").
 *
 * Scopes to whatever BranchContext resolved for this request — the
 * caller's own branch by default, or a widened scope when an owner
 * explicitly asked for one via ?branch=all / ?branch={ulid}. A no-op
 * otherwise, so console commands, seeders, and system/cross-branch jobs
 * still see everything.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $branchId = app(BranchContext::class)->branchId();

        if ($branchId !== null) {
            $builder->where($model->qualifyColumn('branch_id'), $branchId);
        }
    }
}
