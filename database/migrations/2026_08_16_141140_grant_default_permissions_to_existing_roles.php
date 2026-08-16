<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time upgrade step for installations that already had member_org /
     * nep_coordinator roles before programmes.verify, programmes.export,
     * programmes.export-all, and organisation-profile.view/update were
     * added to their default permission sets.
     *
     * RolePermissionSeeder::ensureBuiltInRole() only ever seeds a role's
     * default permissions at the moment the role is first created — on
     * purpose, so re-running the seeder never overwrites permissions an
     * admin has since added or removed through the Roles UI (see that
     * class for the full reasoning). That means a brand new default
     * permission for an *already-existing* role needs a real, one-time
     * migration to reach installations that predate it — which is exactly
     * what this is. It only ever attaches (never detaches, never touches
     * any other permission on the role), and is a no-op wherever the role
     * or permission doesn't exist yet (a fresh install seeds these
     * correctly from scratch instead, via the first-creation path above).
     */
    public function up(): void
    {
        $grants = [
            'member_org' => ['programmes.verify', 'programmes.export', 'programmes.export-all', 'organisation-profile.view', 'organisation-profile.update'],
            'nep_coordinator' => ['programmes.export', 'programmes.export-all'],
        ];

        foreach ($grants as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }

            $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id', 'name');

            foreach ($permissionNames as $permissionName) {
                $permissionId = $permissionIds[$permissionName] ?? null;
                if (! $permissionId) {
                    continue;
                }

                $alreadyGranted = DB::table('permission_role')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (! $alreadyGranted) {
                    DB::table('permission_role')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op: rolling back can't distinguish "this grant came
     * from this migration" from "an admin deliberately added the same
     * permission through the UI afterward" — removing it here risks
     * silently taking away something an admin intentionally configured.
     */
    public function down(): void
    {
        //
    }
};
