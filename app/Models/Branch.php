<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'code', 'legal_name', 'address_line1', 'address_line2', 'city', 'province',
    'postal_code', 'contact_phone', 'contact_email', 'tin', 'bir_permit_no',
    'vat_registered', 'receipt_header_text', 'receipt_footer_text', 'timezone', 'is_active',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'vat_registered' => 'boolean',
            'is_active' => 'boolean',
        ];
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
