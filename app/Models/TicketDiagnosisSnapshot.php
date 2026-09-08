<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use App\Support\Diagnosis\IssueSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The picture the customer was shown when they approved the quote.
 *
 * Append-only and never edited: the point of it is that it cannot drift when
 * the finding behind it is revised. Not branch-scoped directly — it reaches
 * BranchScope through its ticket.
 */
#[Fillable([
    'repair_ticket_id', 'storage_disk', 'storage_path', 'sha256_hash',
    'issue_source', 'issue_keys', 'part_keys', 'camera', 'note', 'captured_by',
])]
class TicketDiagnosisSnapshot extends Model
{
    use HasUlid;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'issue_source' => IssueSource::class,
            'issue_keys' => 'array',
            'part_keys' => 'array',
            'camera' => 'array',
        ];
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
