<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * The full permission catalogue. Exposed via permissionDefinitions() so
     * other bootstrapping code (see UserFactory) can ensure these rows exist
     * without duplicating the list.
     */
    private static array $permissions = [
        // User management
        ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'Users', 'description' => 'View user accounts'],
        ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'Users', 'description' => 'Create new user accounts'],
        ['name' => 'users.update', 'display_name' => 'Update Users', 'group' => 'Users', 'description' => 'Update user accounts'],
        ['name' => 'users.delete', 'display_name' => 'Delete Users', 'group' => 'Users', 'description' => 'Delete user accounts'],
        ['name' => 'users.assign-roles', 'display_name' => 'Assign Roles', 'group' => 'Users', 'description' => 'Assign roles to users'],

        // Organisation management — organisations.* below is the admin-side
        // "manage any organisation" capability (/admin/organisations/*,
        // unscoped by id). organisation-profile.* is the deliberately
        // separate, narrower "manage MY OWN organisation" capability
        // (/organisations/me, inherently scoped to the caller — there's no
        // id to manipulate). Never grant organisations.update to a
        // non-admin role expecting it to mean "their own org only" — it
        // means every organisation, by design.
        ['name' => 'organisations.view', 'display_name' => 'View Organisations', 'group' => 'Organisations', 'description' => 'View organisations'],
        ['name' => 'organisations.create', 'display_name' => 'Create Organisations', 'group' => 'Organisations', 'description' => 'Create new organisations'],
        ['name' => 'organisations.update', 'display_name' => 'Update Organisations', 'group' => 'Organisations', 'description' => 'Update organisations'],
        ['name' => 'organisations.delete', 'display_name' => 'Delete Organisations', 'group' => 'Organisations', 'description' => 'Delete organisations'],
        ['name' => 'organisation-profile.view', 'display_name' => "View Own Organisation Profile", 'group' => 'Organisations', 'description' => "View the caller's own organisation profile"],
        ['name' => 'organisation-profile.update', 'display_name' => "Update Own Organisation Profile", 'group' => 'Organisations', 'description' => "Update the caller's own organisation profile"],

        // Programme management
        ['name' => 'programmes.view', 'display_name' => 'View Programmes', 'group' => 'Programmes', 'description' => 'View programme entries'],
        // Deliberately narrow, same shape as advisory.view vs advisory.view-all
        // above: a holder of programmes.view-own only ever sees entries they
        // personally created (programme_entries.created_by), regardless of
        // whether they'd otherwise have organisation-wide access (e.g. a
        // staff-like role with no organisation_id of its own). Checked before
        // the organisation-wide/own-org fallback in
        // ScopesProgrammeEntryAccess and ProgrammeEntryController — see there
        // for the actual scoping logic.
        ['name' => 'programmes.view-own', 'display_name' => 'View Own Programmes Only', 'group' => 'Programmes', 'description' => 'View only programme entries the user personally created, regardless of organisation'],
        ['name' => 'programmes.create', 'display_name' => 'Create Programmes', 'group' => 'Programmes', 'description' => 'Create programme entries'],
        ['name' => 'programmes.update', 'display_name' => 'Update Programmes', 'group' => 'Programmes', 'description' => 'Update programme entries'],
        ['name' => 'programmes.verify', 'display_name' => 'Verify Programmes', 'group' => 'Programmes', 'description' => 'Verify programme entries'],
        // Split from programmes.view: being able to see an entry doesn't
        // imply being able to export it as a PDF — kept as its own
        // permission so an admin can grant/revoke export independently
        // (e.g. programmes.view-own holders never get export at all,
        // vs. programmes.view holders who may or may not).
        ['name' => 'programmes.export', 'display_name' => 'Export Programme PDF', 'group' => 'Programmes', 'description' => 'Export a single programme entry as PDF'],
        ['name' => 'programmes.export-all', 'display_name' => 'Export All Organisation Programmes PDF', 'group' => 'Programmes', 'description' => "Export an organisation's full set of programme entries as PDF"],

        // Advisory management
        // advisory.view is deliberately narrow: it's what a member_org user
        // holds to view the delivered advisory note for their OWN programme
        // entry (GET /adviser/programme-entries/{id}/advisory-note).
        // advisory.view-all is the separate, broader "browse the whole
        // Adviser workflow" capability (submission list/detail/exports) —
        // conflating the two would mean granting a member org "view my own
        // delivered note" also silently unlocks every organisation's
        // in-progress submissions.
        ['name' => 'advisory.view', 'display_name' => 'View Own Advisory Note', 'group' => 'Advisory', 'description' => "View the delivered advisory note for the user's own programme entries"],
        ['name' => 'advisory.view-all', 'display_name' => 'View All Advisory Submissions', 'group' => 'Advisory', 'description' => 'Browse and view every Adviser submission across all organisations'],
        ['name' => 'advisory.create', 'display_name' => 'Create Advisory', 'group' => 'Advisory', 'description' => 'Create advisory submissions'],
        ['name' => 'advisory.update', 'display_name' => 'Update Advisory', 'group' => 'Advisory', 'description' => 'Update advisory submissions'],
        ['name' => 'advisory.deliver', 'display_name' => 'Deliver Advisory', 'group' => 'Advisory', 'description' => 'Deliver advisory notes'],

        // Role management
        ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'Roles', 'description' => 'View roles'],
        ['name' => 'roles.create', 'display_name' => 'Create Roles', 'group' => 'Roles', 'description' => 'Create new roles'],
        ['name' => 'roles.update', 'display_name' => 'Update Roles', 'group' => 'Roles', 'description' => 'Update roles'],
        ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'group' => 'Roles', 'description' => 'Delete roles'],
        ['name' => 'roles.assign', 'display_name' => 'Assign Roles', 'group' => 'Roles', 'description' => 'Assign roles to users'],

        // Permission management
        ['name' => 'permissions.view', 'display_name' => 'View Permissions', 'group' => 'Permissions', 'description' => 'View permissions'],
        ['name' => 'permissions.create', 'display_name' => 'Create Permissions', 'group' => 'Permissions', 'description' => 'Create new permissions'],
        ['name' => 'permissions.update', 'display_name' => 'Update Permissions', 'group' => 'Permissions', 'description' => 'Update permissions'],
        ['name' => 'permissions.delete', 'display_name' => 'Delete Permissions', 'group' => 'Permissions', 'description' => 'Delete permissions'],

        // Taxonomy management
        ['name' => 'taxonomy.view', 'display_name' => 'View Taxonomy', 'group' => 'Taxonomy', 'description' => 'View taxonomy'],
        ['name' => 'taxonomy.create', 'display_name' => 'Create Taxonomy', 'group' => 'Taxonomy', 'description' => 'Create taxonomy items'],
        ['name' => 'taxonomy.update', 'display_name' => 'Update Taxonomy', 'group' => 'Taxonomy', 'description' => 'Update taxonomy items'],
        ['name' => 'taxonomy.delete', 'display_name' => 'Delete Taxonomy', 'group' => 'Taxonomy', 'description' => 'Delete taxonomy items'],
        ['name' => 'taxonomy.review-other', 'display_name' => 'Review "Other" Entries', 'group' => 'Taxonomy', 'description' => 'Review free-text "Other" taxonomy submissions for annual curation'],

        // Dashboard and reports
        ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'group' => 'Dashboard', 'description' => 'View dashboard and statistics'],
        ['name' => 'reports.view', 'display_name' => 'View Reports', 'group' => 'Reports', 'description' => 'View reports'],
        ['name' => 'reports.export', 'display_name' => 'Export Reports', 'group' => 'Reports', 'description' => 'Export reports'],

        // Audit trail
        ['name' => 'audit-logs.view', 'display_name' => 'View Audit Logs', 'group' => 'Audit', 'description' => 'View the audit trail of destructive actions'],

        // Policy Library — replaces the hardcoded role:nep_admin,nep_coordinator,
        // member_org / role:nep_admin,nep_coordinator / role:nep_admin gates
        // that used to be the only way in for these routes (see routes/api.php).
        ['name' => 'policy.view', 'display_name' => 'View Policy Library', 'group' => 'Policy Library', 'description' => 'View policy documents'],
        ['name' => 'policy.create', 'display_name' => 'Add Policy Documents', 'group' => 'Policy Library', 'description' => 'Upload new policy documents'],
        ['name' => 'policy.update', 'display_name' => 'Update Policy Documents', 'group' => 'Policy Library', 'description' => 'Edit existing policy documents'],
        ['name' => 'policy.delete', 'display_name' => 'Delete Policy Documents', 'group' => 'Policy Library', 'description' => 'Delete policy documents'],
    ];

    /**
     * display_name/description/is_system/is_super_admin for each built-in role.
     */
    private static array $roleDefinitions = [
        'nep_admin' => [
            'display_name' => 'NEP Administrator',
            'description' => 'Full system access with all permissions',
            'is_system' => true,
            'is_super_admin' => true,
        ],
        'nep_coordinator' => [
            'display_name' => 'NEP Coordinator',
            'description' => 'Organisation-level access with limited administrative functions',
            'is_system' => true,
            'is_super_admin' => false,
        ],
        'member_org' => [
            'display_name' => 'Member Organisation',
            'description' => 'Basic access to assigned programmes only',
            'is_system' => false,
            'is_super_admin' => false,
        ],
    ];

    /**
     * Permission names granted to each non-super-admin built-in role.
     * nep_admin needs no entry — is_super_admin holds every permission implicitly.
     */
    private static array $rolePermissionNames = [
        'nep_coordinator' => [
            'users.view',
            'organisations.view',
            'programmes.view',
            'programmes.create',
            'programmes.update',
            // Coordinators already reach both PDF export routes today via
            // programmes.view; export is now its own permission (see
            // programmes.export/-all above) so these two grants preserve
            // that existing capability rather than silently taking it away.
            'programmes.export',
            'programmes.export-all',
            'advisory.view',
            'advisory.view-all',
            'advisory.create',
            'advisory.update',
            // Coordinators deliver advisory notes as part of their normal
            // workflow — needed now that /adviser/submissions/{id}/deliver
            // is gated by this permission specifically rather than a
            // hardcoded role check.
            'advisory.deliver',
            'dashboard.view',
            'reports.view',
            'reports.export',
            'taxonomy.view',
            'policy.view',
            'policy.create',
            'policy.update',
        ],
        'member_org' => [
            'programmes.view',
            'programmes.create',
            'programmes.update',
            // Verify/export are scoped to "own organisation" the same way
            // view/create/update already are — see ScopesProgrammeEntryAccess
            // for the actual ownership check applied on top of this
            // permission grant (organisation_id === user.organisation_id).
            'programmes.verify',
            'programmes.export',
            'programmes.export-all',
            'advisory.view',
            // Needed to populate the taxonomy pickers on the programme entry
            // form — previously granted implicitly by a hardcoded
            // role:nep_admin,nep_coordinator,member_org route check.
            'taxonomy.view',
            // /map/entries and its exports are documented and already
            // implemented (BuildsMapQuery) to scope member_org to their own
            // organisation's entries — the route itself just never actually
            // let them through before.
            'reports.view',
            'reports.export',
            'policy.view',
            // Distinct from organisations.* (the admin "manage any
            // organisation" capability) — this only ever unlocks
            // /organisations/me, which is inherently scoped to the caller's
            // own organisation regardless of this permission.
            'organisation-profile.view',
            'organisation-profile.update',
        ],
    ];

    /** @return array<int, array{name: string, display_name: string, group: string, description: string}> */
    public static function permissionDefinitions(): array
    {
        return self::$permissions;
    }

    /**
     * Create one of the three built-in roles if it doesn't exist yet, and
     * seed its *default* permission set only at that moment of creation.
     * Used by run() and reused by UserFactory so factory-created users
     * (`User::factory()->create(['role' => 'member_org'])`) get the same
     * real permissions a freshly seeded deployment would have, instead of
     * duplicating this list in test fixtures.
     *
     * Deliberately does NOT re-sync permissions for a role that already
     * exists: an admin may have added or removed permissions through the
     * Roles UI since this role was first created, and re-running the seeder
     * (or, via UserFactory, simply creating another test user) must never
     * silently overwrite that. A newly-introduced default permission for an
     * existing installation's built-in role is a one-time upgrade concern,
     * not something this idempotent seeder handles — see the
     * grant_default_permissions_to_existing_roles migration for how that's
     * done instead (additive-only, never removes).
     */
    public static function ensureBuiltInRole(string $roleName): ?Role
    {
        if (! isset(self::$roleDefinitions[$roleName])) {
            return null;
        }

        foreach (self::$permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        $existingRole = Role::where('name', $roleName)->first();
        $role = $existingRole ?? Role::create(['name' => $roleName, ...self::$roleDefinitions[$roleName]]);

        if ($existingRole === null && ! $role->is_super_admin) {
            $names = self::$rolePermissionNames[$roleName] ?? [];
            $role->permissions()->sync(Permission::whereIn('name', $names)->pluck('id'));
        }

        return $role;
    }

    public function run(): void
    {
        // firstOrCreate, not updateOrInsert: an admin can rename a
        // permission's display_name/description via permissions.update —
        // re-seeding must not revert that on an already-existing row,
        // same principle as ensureBuiltInRole() below not re-syncing an
        // existing role's permission set.
        foreach (self::$permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        foreach (array_keys(self::$roleDefinitions) as $roleName) {
            static::ensureBuiltInRole($roleName);
        }

        // Admin holds every permission implicitly via is_super_admin, but
        // keep the pivot rows populated too so `roles.permissions` reads
        // (e.g. the admin UI) show the full list rather than an empty one.
        $admin = Role::where('name', 'nep_admin')->first();
        if ($admin) {
            $admin->permissions()->sync(Permission::all()->pluck('id'));
        }

        // Backfill and repair the role relation for users created before RBAC existed.
        User::query()->select(['id', 'role'])->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $user->syncLegacyRole();
            }
        });
    }
}
