<?php

namespace App\Models;

use Database\Factories\DocumentPrintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_type', 'printable_type', 'printable_id', 'kind', 'sequence_no', 'printed_by', 'printed_at'])]
class DocumentPrint extends Model
{
    /** @use HasFactory<DocumentPrintFactory> */
    use HasFactory;

    public $timestamps = false;

    public const TYPES = [
        'claim_stub', 'acknowledgment_receipt', 'warranty_slip',
        'job_order', 'unclaimed_notice', 'shift_report',
    ];

    protected function casts(): array
    {
        return ['printed_at' => 'datetime'];
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
