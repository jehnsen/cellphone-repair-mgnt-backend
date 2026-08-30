<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Branch-scoped key/value config with a global fallback: a row with
 * branch_id = null is the shop-wide default, a row with a branch_id
 * overrides it for that branch (see docs/design/01-domain-design.md §2.1).
 * No ULID — a setting is addressed by (branch_id, key), never exposed as a
 * standalone record. Deliberately NOT #[ScopedBy(BranchScope)]: the global
 * rows must stay visible to every branch, so SettingRepository does the
 * scoping by hand.
 */
#[Fillable(['branch_id', 'key', 'value', 'type'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    public const TYPES = ['string', 'int', 'decimal', 'bool', 'json'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Best-effort `type` tag for a raw PHP value, so a bulk PUT that omits
     * an explicit type still records something sensible.
     */
    public static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'decimal',
            is_string($value) => 'string',
            default => 'json',
        };
    }
}
