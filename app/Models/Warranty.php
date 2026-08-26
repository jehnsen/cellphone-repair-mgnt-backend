<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\WarrantyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['repair_ticket_id', 'scope', 'days', 'issued_at', 'expiry_date', 'exclusions', 'warranty_code'])]
class Warranty extends Model
{
    /** @use HasFactory<WarrantyFactory> */
    use HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expiry_date' => 'date',
        ];
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class);
    }
}
