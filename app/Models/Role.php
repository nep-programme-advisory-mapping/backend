<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
        'is_super_admin',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_super_admin' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * A role flagged is_super_admin holds every permission implicitly and is
     * kept in sync whenever a new permission is created (see Permission::booted()).
     * This is the data-driven replacement for hardcoded "role === admin" checks.
     */
    public function scopeSuperAdmin($query)
    {
        return $query->where('is_super_admin', true);
    }
}