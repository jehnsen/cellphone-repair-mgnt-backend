<?php

namespace App\Models;

use App\Support\Diagnosis\IssueSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "This issue means that part." Joins one key from a controlled vocabulary
 * (an intake problem tag, or a finding defect) to one device part.
 *
 * No ULID and no route binding: rows are never addressed individually from
 * the client, only replaced wholesale for one issue key.
 */
#[Fillable(['issue_source', 'issue_key', 'device_part_id', 'rank'])]
class IssuePartLink extends Model
{
    protected $table = 'issue_part_map';

    protected function casts(): array
    {
        return [
            'issue_source' => IssueSource::class,
            'rank' => 'integer',
        ];
    }

    public function devicePart(): BelongsTo
    {
        return $this->belongsTo(DevicePart::class);
    }

    /** @param Builder<self> $query */
    public function scopeForIssue(Builder $query, IssueSource $source, string $key): void
    {
        $query->where('issue_source', $source->value)->where('issue_key', $key);
    }
}
