<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $coordinatorUser;
    private User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $adminRole = Role::create([
            'name' => 'nep_admin',
            'display_name' => 'NEP Administrator',
            'description' => 'Full system access',
            'is_system' => true,
            'is_super_admin' => true,
        ]);

        $coordinatorRole = Role::create([
            'name' => 'nep_coordinator',
            'display_name' => 'NEP Coordinator',
            'description' => 'Organisation-level access',
            'is_system' => true,
        ]);

        $memberRole = Role::create([
            'name' => 'member_org',
            'display_name' => 'Member Organisation',
            'description' => 'Basic access',
            'is_system' => true,
        ]);

        // Create permissions
        $permissions = [
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'Users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'Users'],
            ['name' => 'users.update', 'display_name' => 'Update Users', 'group' => 'Users'],
            ['name' => 'users.assign-roles', 'display_name' => 'Assign Roles', 'group' => 'Users'],
            ['name' => 'organisations.view', 'display_name' => 'View Organisations', 'group' => 'Organisations'],
            ['name' => 'organisations.create', 'display_name' => 'Create Organisations', 'group' => 'Organisations'],
            ['name' => 'programmes.view', 'display_name' => 'View Programmes', 'group' => 'Programmes'],
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'Roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'group' => 'Roles'],
            ['name' => 'roles.update', 'display_name' => 'Update Roles', 'group' => 'Roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'group' => 'Roles'],
            ['name' => 'roles.assign', 'display_name' => 'Assign Roles', 'group' => 'Roles'],
            ['name' => 'permissions.view', 'display_name' => 'View Permissions', 'group' => 'Permissions'],
            ['name' => 'permissions.create', 'display_name' => 'Create Permissions', 'group' => 'Permissions'],
            ['name' => 'permissions.update', 'display_name' => 'Update Permissions', 'group' => 'Permissions'],
            ['name' => 'permissions.delete', 'display_name' => 'Delete Permissions', 'group' => 'Permissions'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Assign all permissions to admin
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));

        // Assign limited permissions to coordinator
        $coordinatorRole->permissions()->sync(
            Permission::whereIn('name', ['users.view', 'programmes.view', 'roles.view', 'organisations.view'])->pluck('id')
        );

        // Assign minimal permissions to member
        $memberRole->permissions()->sync(
            Permission::whereIn('name', ['programmes.view'])->pluck('id')
        );

        // Create users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'nep_admin',
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($adminRole);

        $this->coordinatorUser = User::create([
            'name' => 'Coordinator User',
            'email' => 'coordinator@test.com',
            'password' => bcrypt('password'),
            'role' => 'nep_coordinator',
            'status' => 'active',
        ]);
        $this->coordinatorUser->roles()->attach($coordinatorRole);

        $this->memberUser = User::create([
            'name' => 'Member User',
            'email' => 'member@test.com',
            'password' => bcrypt('password'),
            'role' => 'member_org',
            'status' => 'active',
        ]);
        $this->memberUser->roles()->attach($memberRole);
    }

    public function test_admin_can_view_all_users(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'status', 'organisation_id']
                ]
            ]);
    }

    public function test_coordinator_can_view_users_when_permission_is_assigned(): void
    {
        $this->actingAs($this->coordinatorUser, 'sanctum');

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200);
    }

    public function test_member_cannot_view_users(): void
    {
        $this->actingAs($this->memberUser, 'sanctum');

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'role' => 'member_org',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role']
            ]);
    }

    public function test_admin_can_assign_role_to_user(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $role = Role::where('name', 'nep_coordinator')->first();
        
        $response = $this->postJson("/api/admin/roles/{$role->id}/users/{$this->memberUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Role assigned to user successfully.'
            ]);

        $this->assertTrue($this->memberUser->roles()->where('role_id', $role->id)->exists());
    }

    public function test_admin_can_view_roles(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'display_name', 'permissions']
            ]);
    }

    public function test_admin_can_manage_permissions(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        // View permissions
        $response = $this->getJson('/api/admin/permissions');
        $response->assertStatus(200);

        // Create permission
        $response = $this->postJson('/api/admin/permissions', [
            'name' => 'test.permission',
            'display_name' => 'Test Permission',
            'group' => 'Testing',
        ]);
        $response->assertStatus(201);

        // Update permission
        $permission = Permission::where('name', 'test.permission')->first();
        $response = $this->patchJson("/api/admin/permissions/{$permission->id}", [
            'display_name' => 'Updated Test Permission',
        ]);
        $response->assertStatus(200);

        // Delete permission
        $response = $this->deleteJson("/api/admin/permissions/{$permission->id}");
        $response->assertStatus(200);
    }

    public function test_user_has_permission_method_works(): void
    {
        // Admin should have all permissions
        $this->assertTrue($this->adminUser->hasPermission('users.view'));
        $this->assertTrue($this->adminUser->hasPermission('users.create'));
        $this->assertTrue($this->adminUser->hasPermission('organisations.create'));

        // Coordinator should have limited permissions
        $this->assertTrue($this->coordinatorUser->hasPermission('users.view'));
        $this->assertFalse($this->coordinatorUser->hasPermission('users.create'));
        $this->assertFalse($this->coordinatorUser->hasPermission('organisations.create'));

        // Member should have minimal permissions
        $this->assertTrue($this->memberUser->hasPermission('programmes.view'));
        $this->assertFalse($this->memberUser->hasPermission('users.view'));
    }

    public function test_user_has_role_method_works(): void
    {
        $this->assertTrue($this->adminUser->hasRole('nep_admin'));
        $this->assertTrue($this->coordinatorUser->hasRole('nep_coordinator'));
        $this->assertTrue($this->memberUser->hasRole('member_org'));
        
        $this->assertFalse($this->adminUser->hasRole('nep_coordinator'));
    }

    public function test_unauthorized_actions_are_blocked(): void
    {
        // Test that member cannot access admin routes
        $this->actingAs($this->memberUser, 'sanctum');

        $this->getJson('/api/admin/users')->assertStatus(403);
        $this->getJson('/api/admin/roles')->assertStatus(403);
        $this->getJson('/api/admin/permissions')->assertStatus(403);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $adminRole = Role::where('name', 'nep_admin')->first();

        $response = $this->deleteJson("/api/admin/roles/{$adminRole->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'System roles cannot be deleted.'
            ]);
    }

    public function test_system_roles_cannot_be_modified(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $adminRole = Role::where('name', 'nep_admin')->first();

        $response = $this->patchJson("/api/admin/roles/{$adminRole->id}", [
            'display_name' => 'Modified Admin',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'System roles cannot be modified.'
            ]);
    }

    public function test_system_role_permissions_can_be_updated(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $coordinatorRole = Role::where('name', 'nep_coordinator')->first();
        $permission = Permission::where('name', 'organisations.create')->first();

        $response = $this->patchJson("/api/admin/roles/{$coordinatorRole->id}", [
            'permissions' => [$permission->id],
        ]);

        $response->assertStatus(200);

        $coordinatorRole->refresh();
        $this->assertTrue($coordinatorRole->permissions()->where('permissions.id', $permission->id)->exists());
        $this->assertEquals('nep_coordinator', $coordinatorRole->name); // identity untouched
    }

    public function test_empty_permissions_array_clears_role_permissions(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $coordinatorRole = Role::where('name', 'nep_coordinator')->first();
        $permission = Permission::where('name', 'users.view')->first();
        $coordinatorRole->permissions()->sync([$permission->id]);

        $this->patchJson("/api/admin/roles/{$coordinatorRole->id}", [
            'permissions' => [],
        ])->assertStatus(200);

        $coordinatorRole->refresh();
        $this->assertCount(0, $coordinatorRole->permissions);
    }

    public function test_coordinator_can_view_organisations(): void
    {
        $this->actingAs($this->coordinatorUser, 'sanctum');

        $response = $this->getJson('/api/admin/organisations');

        $response->assertStatus(200);
    }

    public function test_member_cannot_create_organisations(): void
    {
        $this->actingAs($this->memberUser, 'sanctum');

        $response = $this->postJson('/api/admin/organisations', [
            'name' => 'Test Organisation',
        ]);

        $response->assertStatus(403);
    }

    public function test_role_permissions_are_enforced_by_backend(): void
    {
        // Test that backend enforces permissions even if frontend allows
        $this->actingAs($this->memberUser, 'sanctum');

        // Member should not be able to create users even if they try
        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'role' => 'member_org',
        ]);

        $response->assertStatus(403);
    }
}
