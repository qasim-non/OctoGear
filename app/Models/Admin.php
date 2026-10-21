<?php

namespace App\Models;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Enums\PreferredLanguage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Laravel guesses "admins" from "Admin", but our table is "admin" (singular).
     */
    public function getTable(): string
    {
        return 'admin';
    }

    /**
     * The table uses 'employee_id' as primary key instead of 'id'.
     */
    public function getKeyName(): string
    {
        return 'employee_id';
    }

    protected $fillable = [
        'name',
        'assigned_role',
        'mobile',
        'email',
        'password',
        'status',
        'preferred_language',
    ];

    /**
     * password is hidden so it never leaks in API responses or JSON output.
     */
    protected $hidden = [
        'password',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',          // Auto-hashes on set: plain text → bcrypt
            'assigned_role' => AdminRole::class,
            'status' => AdminStatus::class,
            'preferred_language' => PreferredLanguage::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function isBlocked(): bool
    {
        return $this->status === AdminStatus::Blocked;
    }

    public function isActive(): bool
    {
        return $this->status === AdminStatus::Active;
    }
}
