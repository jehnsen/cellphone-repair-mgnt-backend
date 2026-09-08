<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One part of the generic phone rig the diagnosis visualizer draws.
 *
 * Shop-wide reference data, not branch-scoped: a battery is a battery at
 * either site. Never deleted in practice — a part a stored snapshot names
 * has to keep resolving, so management deactivates instead.
 */
#[Fillable([
    'key', 'label', 'category', 'blurb',
    'pos_x', 'pos_y', 'pos_z',
    'size_x', 'size_y', 'size_z',
    'explode_x', 'explode_y', 'explode_z', 'explode_distance',
    'sort_order', 'is_active',
])]
class DevicePart extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'pos_x' => 'float',
            'pos_y' => 'float',
            'pos_z' => 'float',
            'size_x' => 'float',
            'size_y' => 'float',
            'size_z' => 'float',
            'explode_x' => 'float',
            'explode_y' => 'float',
            'explode_z' => 'float',
            'explode_distance' => 'float',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function issueLinks(): HasMany
    {
        return $this->hasMany(IssuePartLink::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** The order the rig is listed and drawn in — front of the device first. */
    public function scopeInAssemblyOrder(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
