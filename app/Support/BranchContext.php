<?php

namespace App\Support;

use App\Models\Branch;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Http\Request;

/**
 * Resolves which branch(es) the current request may see, and is the one
 * thing BranchScope consults.
 *
 * Default stays exactly what it always was — the caller's own branch. A
 * caller holding `branches.view_all` (owner) may widen it explicitly:
 *
 *   ?branch=all      every branch
 *   ?branch={ulid}   that one branch
 *
 * Nothing widens implicitly: an owner who sends no `branch` parameter
 * still gets their own branch, so no existing response shape changes.
 * A caller without the permission asking for anything but their own
 * branch gets 403 rather than a silently-narrowed result — a report that
 * quietly answers a different question than the one asked is worse than
 * an error.
 *
 * Set once per request by App\Http\Middleware\ResolveBranchContext.
 */
class BranchContext
{
    /** Query parameter clients use to widen or switch scope. */
    public const PARAM = 'branch';

    /** Value that means "every branch". */
    public const ALL = 'all';

    /** Null = unrestricted (all branches). Otherwise the single branch id to scope to. */
    private ?int $branchId = null;

    private bool $resolved = false;

    /** True when this request was explicitly widened to every branch. */
    private bool $spansAllBranches = false;

    public function resolve(Request $request): void
    {
        $user = $request->user();

        // Unauthenticated (console, seeders, the public verify route):
        // unrestricted, same as BranchScope has always behaved.
        if ($user === null) {
            $this->set(null, spansAll: true);

            return;
        }

        $requested = $request->query(self::PARAM);

        if ($requested === null || $requested === '') {
            $this->set($user->branch_id, spansAll: false);

            return;
        }

        if (! $user->can('branches.view_all')) {
            throw new ApiException(
                ErrorCode::Forbidden,
                'You do not have permission to view other branches.',
            );
        }

        if ($requested === self::ALL) {
            $this->set(null, spansAll: true);

            return;
        }

        // A specific branch, addressed by ulid like every other FK in this
        // API — never an internal id.
        $branchId = Branch::query()->where('ulid', $requested)->value('id');

        if ($branchId === null) {
            throw new ApiException(ErrorCode::NotFound, 'No branch matches that identifier.');
        }

        $this->set($branchId, spansAll: false);
    }

    private function set(?int $branchId, bool $spansAll): void
    {
        $this->branchId = $branchId;
        $this->spansAllBranches = $spansAll;
        $this->resolved = true;
    }

    /**
     * The branch id to constrain queries to, or null for every branch.
     * Falls back to the authenticated user's own branch when the resolver
     * never ran (a non-HTTP path, or a request that bypassed middleware),
     * so this can never accidentally widen anything.
     */
    public function branchId(): ?int
    {
        if (! $this->resolved) {
            return auth()->check() ? auth()->user()?->branch_id : null;
        }

        return $this->branchId;
    }

    public function spansAllBranches(): bool
    {
        return $this->resolved && $this->spansAllBranches;
    }
}
