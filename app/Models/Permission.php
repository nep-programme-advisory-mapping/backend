<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'group',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }

    protected static function booted(): void
    {
        // Keep super admin role(s) complete: a newly-defined permission is
        // automatically granted to every super admin role so the highest
        // privilege account never "loses" a permission just because it was
        // created after the role was.
        static::created(function (Permission $permission) {
            Role::superAdmin()->get()->each(
                fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id])
            );
        });
    }
}