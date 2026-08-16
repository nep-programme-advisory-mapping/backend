<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the "dynamic, admin-manageable" surface of the RBAC system: an
 * admin can create/edit/delete roles and permissions entirely through data,
 * with no source changes, while the highest-privilege account stays safe.
 */
class RoleManagementCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private Role $superAdminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create([
            'name' => 'nep_admin',
            'display_name' => 'NEP Administrator',
            'is_system' => true,
            'is_super_admin' => true,
        ]);

        foreach ([
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'Roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'group' => 'Roles'],
            ['name' => 'roles.update', 'display_name' => 'Update Roles', 'group' => 'Roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'group' => 'Roles'],
            ['name' => 'roles.assign', 'display_name' => 'Assign Roles', 'group' => 'Roles'],
            ['name' => 'permissions.view', 'display_name' => 'View Permissions', 'group' => 'Permissions'],
            ['name' => 'permissions.create', 'display_name' => 'Create Permissions', 'group' => 'Permissions'],
            ['name' => 'permissions.update', 'display_name' => 'Update Permissions', 'group' => 'Permissions'],
            ['name' => 'permissions.delete', 'display_name' => 'Delete Permissions', 'group' => 'Permissions'],
            ['name' => 'programmes.view', 'display_name' => 'View Programmes', 'group' => 'Programmes'],
            ['name' => 'taxonomy.view', 'display_name' => 'View Taxonomy', 'group' => 'Taxonomy'],
        ] as $permission) {
            Permission::create($permission);
        }

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@test.com',
            'password' => bcrypt('password'),
            'role' => 'nep_admin',
            'status' => 'active',
        ]);
        $this->superAdmin->roles()->attach($this->superAdminRole);
    }

    // ─── Role CRUD ──────────────────────────────────────────────────────────

    public function test_admin_can_create_a_new_role_entirely_from_data(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'programme_manager',
            'display_name' => 'Programme Manager',
            'description' => 'Manages programme entries',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'programme_manager')
            ->assertJsonPath('is_system', false);

        $this->assertDatabaseHas('roles', ['name' => 'programme_manager', 'is_system' => false]);
    }

    public function test_admin_can_assign_multiple_permissions_to_a_role(): void
    {
        $role = Role::create(['name' => 'custom_role', 'display_name' => 'Custom Role']);
        $ids = Permission::whereIn('name', ['programmes.view', 'roles.view'])->pluck('id');

        $this->actingAs($this->superAdmin, 'sanctum');

        $response = $this->patchJson("/api/admin/roles/{$role->id}", [
            'permissions' => $ids->all(),
        ]);

        $response->assertStatus(200);
        $role->refresh();
        $this->assertCount(2, $role->permissions);
    }

    public function test_admin_can_remove_a_permission_from_a_role(): void
    {
        $role = Role::create(['name' => 'custom_role', 'display_name' => 'Custom Role']);
        $viewId = Permission::where('name', 'programmes.view')->value('id');
        $role->permissions()->sync([$viewId]);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/roles/{$role->id}", ['permissions' => []])
            ->assertStatus(200);

        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_admin_can_edit_a_custom_roles_identity(): void
    {
        $role = Role::create(['name' => 'custom_role', 'display_name' => 'Old Name']);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/roles/{$role->id}", [
            'display_name' => 'New Name',
            'description' => 'Updated',
        ])->assertStatus(200)
            ->assertJsonPath('display_name', 'New Name');
    }

    public function test_system_role_identity_cannot_be_edited_but_permissions_can(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        // Renaming a system role is blocked.
        $this->patchJson("/api/admin/roles/{$this->superAdminRole->id}", [
            'display_name' => 'Hacked Name',
        ])->assertStatus(422);

        // Adjusting its permission set is allowed.
        $id = Permission::where('name', 'programmes.view')->value('id');
        $this->patchJson("/api/admin/roles/{$this->superAdminRole->id}", [
            'permissions' => [$id],
        ])->assertStatus(200);
    }

    public function test_admin_can_delete_a_role_with_no_users_assigned(): void
    {
        $role = Role::create(['name' => 'throwaway', 'display_name' => 'Throwaway']);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->deleteJson("/api/admin/roles/{$role->id}")->assertStatus(200);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_deleting_a_role_still_assigned_to_users_is_blocked(): void
    {
        $role = Role::create(['name' => 'occupied', 'display_name' => 'Occupied']);
        $user = User::create([
            'name' => 'Assignee', 'email' => 'assignee@test.com', 'password' => bcrypt('password'),
            'role' => 'occupied', 'status' => 'active',
        ]);
        $user->roles()->attach($role);

        $this->actingAs($this->superAdmin, 'sanctum');

        $response = $this->deleteJson("/api/admin/roles/{$role->id}");
        $response->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->deleteJson("/api/admin/roles/{$this->superAdminRole->id}")->assertStatus(422);
        $this->assertDatabaseHas('roles', ['id' => $this->superAdminRole->id]);
    }

    public function test_last_super_admin_role_cannot_be_deleted_even_if_unassigned(): void
    {
        // Make it non-system so only the super-admin guard is exercised.
        $this->superAdminRole->update(['is_system' => false]);
        $this->superAdmin->roles()->detach();

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->deleteJson("/api/admin/roles/{$this->superAdminRole->id}")->assertStatus(422);
    }

    public function test_only_a_super_admin_can_create_another_super_admin_role(): void
    {
        $limitedAdmin = User::create([
            'name' => 'Limited Admin', 'email' => 'limited@test.com', 'password' => bcrypt('password'),
            'role' => 'custom', 'status' => 'active',
        ]);
        $limitedRole = Role::create(['name' => 'custom', 'display_name' => 'Custom']);
        $limitedRole->permissions()->sync(Permission::whereIn('name', ['roles.create'])->pluck('id'));
        $limitedAdmin->roles()->attach($limitedRole);

        $this->actingAs($limitedAdmin, 'sanctum');

        $this->postJson('/api/admin/roles', [
            'name' => 'sneaky_super_admin',
            'display_name' => 'Sneaky',
            'is_super_admin' => true,
        ])->assertStatus(403);
    }

    // ─── Permission CRUD ────────────────────────────────────────────────────

    public function test_admin_can_create_a_permission(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->postJson('/api/admin/permissions', [
            'name' => 'reports.export',
            'display_name' => 'Export Reports',
            'group' => 'Reports',
        ])->assertStatus(201);

        $this->assertDatabaseHas('permissions', ['name' => 'reports.export']);
    }

    public function test_new_permission_is_automatically_granted_to_super_admin_roles(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->postJson('/api/admin/permissions', [
            'name' => 'reports.export',
            'display_name' => 'Export Reports',
            'group' => 'Reports',
        ]);

        $this->assertTrue($this->superAdmin->fresh()->hasPermission('reports.export'));
    }

    public function test_admin_can_update_a_permission(): void
    {
        $permission = Permission::where('name', 'programmes.view')->first();

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/permissions/{$permission->id}", [
            'display_name' => 'View All Programmes',
        ])->assertStatus(200)
            ->assertJsonPath('display_name', 'View All Programmes');
    }

    public function test_duplicate_role_name_is_rejected(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        // nep_admin already exists (seeded built-in role).
        $response = $this->postJson('/api/admin/roles', [
            'name' => 'nep_admin',
            'display_name' => 'Duplicate Admin',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_duplicate_permission_name_is_rejected(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->postJson('/api/admin/permissions', [
            'name' => 'programmes.view', // already exists from setUp
            'display_name' => 'Duplicate',
            'group' => 'Programmes',
        ])->assertStatus(422);
    }

    public function test_admin_can_delete_a_permission(): void
    {
        $permission = Permission::create(['name' => 'temp.thing', 'display_name' => 'Temp', 'group' => 'Temp']);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->deleteJson("/api/admin/permissions/{$permission->id}")->assertStatus(200);
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    // ─── A newly-created dynamic role can actually use what it's granted ────
    // Regression coverage for routes that used to be gated by a hardcoded
    // role:nep_admin,nep_coordinator,member_org list — a custom role could
    // never pass that check no matter what permissions it was given.

    public function test_a_brand_new_custom_role_with_taxonomy_view_can_list_taxonomy_categories(): void
    {
        $customRole = Role::create(['name' => 'field_officer', 'display_name' => 'Field Officer']);
        $customRole->permissions()->sync(Permission::where('name', 'taxonomy.view')->pluck('id'));

        $user = User::create([
            'name' => 'Field Officer', 'email' => 'field@test.com', 'password' => bcrypt('password'),
            'role' => 'field_officer', 'status' => 'active',
        ]);
        $user->roles()->attach($customRole);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/taxonomy/categories');

        $response->assertStatus(200);
    }

    public function test_a_custom_role_without_taxonomy_view_is_blocked_from_taxonomy_categories(): void
    {
        $customRole = Role::create(['name' => 'field_officer', 'display_name' => 'Field Officer']);
        // Granted something unrelated — deliberately not taxonomy.view.
        $customRole->permissions()->sync(Permission::where('name', 'programmes.view')->pluck('id'));

        $user = User::create([
            'name' => 'Field Officer', 'email' => 'field2@test.com', 'password' => bcrypt('password'),
            'role' => 'field_officer', 'status' => 'active',
        ]);
        $user->roles()->attach($customRole);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/taxonomy/categories');

        $response->assertStatus(403);
    }

    // ─── Authorization: 403 without the permission, backend enforces even on direct calls ────

    public function test_user_without_roles_view_permission_gets_403_on_role_list(): void
    {
        $noPermsUser = User::create([
            'name' => 'No Perms', 'email' => 'noperms@test.com', 'password' => bcrypt('password'),
            'role' => 'bare', 'status' => 'active',
        ]);
        Role::create(['name' => 'bare', 'display_name' => 'Bare']);

        $response = $this->actingAs($noPermsUser, 'sanctum')->getJson('/api/admin/roles');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Forbidden. You do not have the required permissions.');
    }

    public function test_direct_api_call_cannot_bypass_permission_middleware(): void
    {
        // Even a well-formed, otherwise-valid request is rejected before it
        // reaches the controller when the caller lacks the permission —
        // simulating someone bypassing the frontend and calling the API directly.
        $noPermsUser = User::create([
            'name' => 'No Perms', 'email' => 'noperms2@test.com', 'password' => bcrypt('password'),
            'role' => 'bare', 'status' => 'active',
        ]);
        Role::create(['name' => 'bare', 'display_name' => 'Bare']);

        $response = $this->actingAs($noPermsUser, 'sanctum')->postJson('/api/admin/roles', [
            'name' => 'should_not_exist',
            'display_name' => 'Should Not Exist',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('roles', ['name' => 'should_not_exist']);
    }
}
