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

    /**
     * Mirrors the column default so a user created without an explicit
     * is_active is active in memory too — the DB default alone leaves the
     * freshly-created model's attribute null until it's re-read, so the
     * 201 from POST /users reported `is_active: null` on an account that
     * was in fact active.
     */
    protected $attributes = [
        'is_active' => true,
    ];

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
