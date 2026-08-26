<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pair with #[ScopedBy(BranchScope::class)] on the model class — the global
 * scope isn't attached here since PHP attributes can't be injected through
 * a trait.
 */
trait BelongsToBranch
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
