<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['branch_id', 'name', 'mobile', 'email', 'address', 'notes', 'is_blacklisted', 'blacklist_reason'])]
#[ScopedBy(BranchScope::class)]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToBranch, HasFactory, HasUlid, SoftDeletes;

    protected function casts(): array
    {
        return ['is_blacklisted' => 'boolean'];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(CustomerDevice::class);
    }

    public function repairTickets(): HasMany
    {
        return $this->hasMany(RepairTicket::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
