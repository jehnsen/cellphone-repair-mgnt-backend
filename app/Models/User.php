<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['branch_id', 'employee_code', 'name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
#[ScopedBy(BranchScope::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToBranch, HasApiTokens, HasFactory, HasRoles, HasUlid, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(RepairTicket::class, 'assigned_technician_id');
    }
}
