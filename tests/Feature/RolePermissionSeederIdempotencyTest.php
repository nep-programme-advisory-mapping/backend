<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The core requirement from the Organization Member permission fix: the
 * seeder establishes *initial* defaults, and must never reset an admin's
 * subsequent customization on a later run — see RolePermissionSeeder's
 * ensureBuiltInRole() docblock for the full reasoning.
 */
class RolePermissionSeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_running_the_seeder_twice_does_not_duplicate_roles(): void
    {
        $countBefore = Role::whereIn('name', ['nep_admin', 'nep_coordinator', 'member_org'])->count();
        $this->assertEquals(3, $countBefore);

        $this->seed(RolePermissionSeeder::class);

        $countAfter = Role::whereIn('name', ['nep_admin', 'nep_coordinator', 'member_org'])->count();
        $this->assertEquals(3, $countAfter);
    }

    public function test_running_the_seeder_twice_does_not_duplicate_permissions(): void
    {
        $countBefore = Permission::where('name', 'programmes.verify')->count();
        $this->assertEquals(1, $countBefore);

        $this->seed(RolePermissionSeeder::class);

        $countAfter = Permission::where('name', 'programmes.verify')->count();
        $this->assertEquals(1, $countAfter);
    }

    public function test_admin_removed_permission_survives_reseeding(): void
    {
        $memberOrg = Role::where('name', 'member_org')->first();
        $verify = Permission::where('name', 'programmes.verify')->first();

        // Simulate an admin using the Roles UI to revoke programmes.verify
        // from Organization Member.
        $memberOrg->permissions()->detach($verify->id);
        $this->assertFalse($memberOrg->permissions()->where('permissions.id', $verify->id)->exists());

        $this->seed(RolePermissionSeeder::class);

        $memberOrg->refresh();
        $this->assertFalse(
            $memberOrg->permissions()->where('permissions.id', $verify->id)->exists(),
            'Reseeding must not silently restore a permission an admin deliberately removed.'
        );
    }

    public function test_admin_added_permission_survives_reseeding(): void
    {
        $memberOrg = Role::where('name', 'member_org')->first();
        $rolesDelete = Permission::where('name', 'roles.delete')->first();

        // Simulate an admin granting Organization Member a permission it
        // doesn't have by default.
        $memberOrg->permissions()->syncWithoutDetaching([$rolesDelete->id]);
        $this->assertTrue($memberOrg->permissions()->where('permissions.id', $rolesDelete->id)->exists());

        $this->seed(RolePermissionSeeder::class);

        $memberOrg->refresh();
        $this->assertTrue(
            $memberOrg->permissions()->where('permissions.id', $rolesDelete->id)->exists(),
            'Reseeding must not silently remove a permission an admin deliberately added.'
        );
    }

    public function test_admin_edited_role_display_name_survives_reseeding(): void
    {
        $memberOrg = Role::where('name', 'member_org')->first();
        $memberOrg->update(['display_name' => 'Org User (renamed by admin)']);

        $this->seed(RolePermissionSeeder::class);

        $memberOrg->refresh();
        $this->assertEquals('Org User (renamed by admin)', $memberOrg->display_name);
    }

    public function test_admin_edited_permission_display_name_survives_reseeding(): void
    {
        $permission = Permission::where('name', 'programmes.verify')->first();
        $permission->update(['display_name' => 'Confirm Programme Is Current']);

        $this->seed(RolePermissionSeeder::class);

        $permission->refresh();
        $this->assertEquals('Confirm Programme Is Current', $permission->display_name);
    }

    public function test_a_brand_new_role_still_gets_its_default_permission_set(): void
    {
        // First-creation path must still work — only re-seeding an
        // *existing* role is a no-op, not creating one for the first time.
        Role::where('name', 'member_org')->delete();
        $this->assertNull(Role::where('name', 'member_org')->first());

        RolePermissionSeeder::ensureBuiltInRole('member_org');

        $memberOrg = Role::where('name', 'member_org')->first();
        $this->assertNotNull($memberOrg);
        $this->assertTrue($memberOrg->permissions()->where('name', 'programmes.view')->exists());
        $this->assertTrue($memberOrg->permissions()->where('name', 'programmes.verify')->exists());
    }
}
