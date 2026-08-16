<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Validation\Rule;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    public const ROLE_NEP_ADMIN = 'nep_admin';
    public const ROLE_NEP_COORDINATOR = 'nep_coordinator';
    public const ROLE_MEMBER_ORG = 'member_org';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'organisation_id',
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Permissions assigned directly to this individual user.
     * When present, they are authoritative over role-derived permissions
     * (see hasPermission).
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role === $roleName || $this->roles()->where('name', $roleName)->exists();
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return in_array($this->role, $roleNames, true) || $this->roles()->whereIn('name', $roleNames)->exists();
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Individually-assigned permissions are authoritative: when the admin
        // explicitly picked abilities for this user, exactly those apply and
        // role-derived permissions are ignored.
        $directPermissions = $this->permissions()->pluck('name');
        if ($directPermissions->isNotEmpty()) {
            return $directPermissions->contains($permissionName);
        }

        $hasPermission = $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();

        if ($hasPermission) {
            return true;
        }

        return Role::query()
            ->where('name', $this->role)
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }

    /**
     * True when the user holds any role flagged is_super_admin — the
     * data-driven replacement for hardcoding `$user->role === 'nep_admin'`.
     * A dynamically-created role can be granted the same status.
     */
    public function isSuperAdmin(): bool
    {
        if ($this->roles()->superAdmin()->exists()) {
            return true;
        }

        return Role::query()->where('name', $this->role)->superAdmin()->exists();
    }

    /**
     * True for a user who should see/manage across every organisation
     * rather than being confined to one — the data-driven scoping signal
     * used throughout the programme-entry/map/dashboard/adviser
     * controllers, replacing hardcoded checks like
     * `in_array($user->role, ['nep_admin', 'nep_coordinator'])`.
     *
     * Two cases: a super admin always has organisation-wide access
     * regardless of whether they also happen to be affiliated with one
     * (e.g. for display purposes); otherwise, a user with no organisation
     * of their own (the convention nep_coordinator and custom "staff-like"
     * roles already follow) is treated as organisation-wide too. A user
     * who does belong to an organisation (member_org, or any custom role
     * assigned one) is confined to it — the actual "can this action happen
     * at all" gate is a separate, permission-based check enforced by route
     * middleware; this only decides *which rows* a user who already passed
     * that gate may reach.
     */
    public function hasOrganisationWideAccess(): bool
    {
        return $this->isSuperAdmin() || $this->organisation_id === null;
    }

    /**
     * True when programmes.view-own should actually narrow what this user
     * sees — deliberately NOT just `hasPermission('programmes.view-own')`:
     * hasPermission() returns true for a super admin on *any* permission
     * name (the bypass that's correct for granting abilities), but
     * programmes.view-own is a *restricting* permission, so that same
     * bypass would wrongly confine a super admin to only entries they
     * personally created. A super admin must always see everything.
     */
    public function wantsOwnProgrammeEntriesOnly(): bool
    {
        return ! $this->isSuperAdmin() && $this->hasPermission('programmes.view-own');
    }

    /**
     * The flat list of permission names this user effectively has, for
     * exposing to the frontend (login/session/user responses) so the UI can
     * build its ability checks without a separate round-trip.
     */
    public function effectivePermissionNames(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::query()->orderBy('name')->pluck('name')->all();
        }

        $directPermissions = $this->permissions()->pluck('name');
        if ($directPermissions->isNotEmpty()) {
            return $directPermissions->sort()->values()->all();
        }

        $roleIds = $this->roles()->pluck('roles.id');
        if ($roleIds->isEmpty()) {
            $legacyRole = Role::query()->where('name', $this->role)->first();
            if ($legacyRole) {
                $roleIds = collect([$legacyRole->id]);
            }
        }

        return Permission::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    public function syncLegacyRole(): void
    {
        $role = Role::query()->where('name', $this->role)->first();

        if ($role) {
            $this->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function isNepAdmin(): bool
    {
        return $this->role === self::ROLE_NEP_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param bool $update Pass true when validating an update, so the
     *                     email uniqueness rule ignores the current user.
     * @param int|null $userId The id of the user being updated (for the email rule).
     */
    public static function validationRules(bool $update = false, ?int $userId = null): array
    {
        return [
            'organisation_id' => [$update ? 'sometimes' : 'nullable', 'exists:organisations,id'],
            'name' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [
                $update ? 'sometimes' : 'required',
                'email',
                'max:255',
                $update ? Rule::unique('users', 'email')->ignore($userId) : 'unique:users,email',
            ],
            'password' => [$update ? 'sometimes' : 'nullable', 'string', 'min:8'],
            // Dynamic: any role that exists in the roles table is a valid primary
            // role — new roles created by an admin work immediately, with no
            // code change or redeploy required.
            'role' => [$update ? 'sometimes' : 'required', 'string', 'exists:roles,name'],
            'status' => ['sometimes', Rule::in([self::STATUS_ACTIVE, self::STATUS_INACTIVE])],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
