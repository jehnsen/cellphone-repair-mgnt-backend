<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\RepairFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per repair ticket (enforced by the unique repair_ticket_id) — the
 * technician's conclusion about what was wrong and what was done, updated
 * in place as work proceeds. Not branch-scoped directly: it reaches
 * BranchScope through its ticket, and is only ever loaded/written by ulid
 * under a specific ticket.
 */
#[Fillable([
    'repair_ticket_id', 'summary', 'details', 'root_cause', 'defects',
    'resolution', 'technician_notes', 'qc_passed', 'qc_checked_at',
    'qc_checked_by_id', 'recorded_by_id',
])]
class RepairFinding extends Model
{
    /** @use HasFactory<RepairFindingFactory> */
    use HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'defects' => 'array',
            'qc_passed' => 'boolean',
            'qc_checked_at' => 'datetime',
        ];
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function qcCheckedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_checked_by_id');
    }
}
