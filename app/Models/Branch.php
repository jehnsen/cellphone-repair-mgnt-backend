<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use App\Support\BranchType;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'code', 'type', 'legal_name', 'address_line1', 'address_line2', 'city', 'province',
    'postal_code', 'contact_phone', 'contact_email', 'tin', 'bir_permit_no',
    'vat_registered', 'receipt_header_text', 'receipt_footer_text', 'timezone', 'is_active',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, HasUlid;

    /**
     * Mirrors the column default so a branch created without an explicit
     * type is a repair branch in memory too — the DB default alone leaves
     * the freshly-created model's `type` null until it's re-read, which
     * would crash BranchResource on the 201 response.
     */
    protected $attributes = [
        'type' => BranchType::RepairAndSales->value,
    ];

    protected function casts(): array
    {
        return [
            'type' => BranchType::class,
            'vat_registered' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Whether the repair surface (job orders, board, part swaps, refurb) exists here. */
    public function offersRepairs(): bool
    {
        return $this->type->offersRepairs();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
