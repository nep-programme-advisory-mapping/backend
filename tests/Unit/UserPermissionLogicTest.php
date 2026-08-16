<?php

namespace Tests\Unit;

use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-level coverage for the RBAC logic on the User model — the source of
 * truth every permission:/role: middleware and the frontend's ability list
 * (via /user's `permissions` field) ultimately reads from. Exercised
 * directly against the model rather than through HTTP, so a regression here
 * is pinpointed immediately instead of surfacing as an unrelated-looking
 * 403 somewhere in the Feature suite.
 */
class UserPermissionLogicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_is_super_admin_true_for_nep_admin_role(): void
    {
        $user = User::factory()->create(['role' => 'nep_admin']);

        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_is_super_admin_false_for_non_admin_roles(): void
    {
        $coordinator = User::factory()->create(['role' => 'nep_coordinator']);
        $member = User::factory()->create(['role' => 'member_org']);

        $this->assertFalse($coordinator->isSuperAdmin());
        $this->assertFalse($member->isSuperAdmin());
    }

    public function test_is_super_admin_true_for_a_custom_role_flagged_as_super_admin(): void
    {
        $role = Role::create([
            'name' => 'system_owner',
            'display_name' => 'System Owner',
            'is_system' => false,
            'is_super_admin' => true,
        ]);
        $user = User::factory()->create(['role' => 'system_owner']);
        $user->roles()->attach($role->id);

        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_has_organisation_wide_access_true_for_super_admin_regardless_of_organisation_id(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create(['role' => 'nep_admin', 'organisation_id' => $organisation->id]);

        $this->assertTrue($user->hasOrganisationWideAccess());
    }

    public function test_has_organisation_wide_access_true_when_organisation_id_is_null(): void
    {
        $user = User::factory()->create(['role' => 'nep_coordinator', 'organisation_id' => null]);

        $this->assertTrue($user->hasOrganisationWideAccess());
    }

    public function test_has_organisation_wide_access_false_for_a_member_org_user(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create(['role' => 'member_org', 'organisation_id' => $organisation->id]);

        $this->assertFalse($user->hasOrganisationWideAccess());
    }

    public function test_effective_permission_names_for_super_admin_is_every_permission(): void
    {
        $user = User::factory()->create(['role' => 'nep_admin']);

        $names = $user->effectivePermissionNames();

        $this->assertEquals(
            Permission::query()->orderBy('name')->pluck('name')->all(),
            $names
        );
    }

    public function test_effective_permission_names_falls_back_to_role_defaults(): void
    {
        $user = User::factory()->create(['role' => 'member_org']);

        $names = $user->effectivePermissionNames();

        $this->assertContains('programmes.view', $names);
        $this->assertContains('programmes.create', $names);
        $this->assertNotContains('users.delete', $names);
    }

    public function test_direct_permission_overrides_take_priority_over_role_defaults(): void
    {
        $user = User::factory()->create(['role' => 'member_org']);
        $onlyPermission = Permission::where('name', 'dashboard.view')->first();
        $user->permissions()->sync([$onlyPermission->id]);

        $names = $user->effectivePermissionNames();

        // Direct grants are authoritative: the role's usual defaults
        // (programmes.view etc.) must NOT leak in alongside them.
        $this->assertEquals(['dashboard.view'], $names);
    }

    public function test_sync_legacy_role_attaches_the_matching_role_row(): void
    {
        $user = User::factory()->create(['role' => 'nep_coordinator']);
        $user->roles()->detach();
        $this->assertCount(0, $user->roles);

        $user->syncLegacyRole();

        $this->assertTrue($user->roles()->where('name', 'nep_coordinator')->exists());
    }

    public function test_is_active_reflects_status_column(): void
    {
        $active = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $inactive = User::factory()->create(['status' => User::STATUS_INACTIVE]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($inactive->isActive());
    }
}
