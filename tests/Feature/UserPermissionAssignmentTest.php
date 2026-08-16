<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'name' => 'nep_admin', 'display_name' => 'NEP Administrator', 'is_system' => true, 'is_super_admin' => true,
        ]);
        $coordinatorRole = Role::create([
            'name' => 'nep_coordinator', 'display_name' => 'NEP Coordinator', 'is_system' => true,
        ]);
        $memberRole = Role::create([
            'name' => 'member_org', 'display_name' => 'Member Organisation', 'is_system' => true,
        ]);

        $permissions = [
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'Users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'Users'],
            ['name' => 'organisations.view', 'display_name' => 'View Organisations', 'group' => 'Organisations'],
            ['name' => 'programmes.view', 'display_name' => 'View Programmes', 'group' => 'Programmes'],
        ];
        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        $coordinatorRole->permissions()->sync(Permission::whereIn('name', ['users.view', 'programmes.view'])->pluck('id'));
        $memberRole->permissions()->sync(Permission::where('name', 'programmes.view')->pluck('id'));

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'nep_admin',
            'status' => 'active',
        ]);
        $this->admin->roles()->attach($adminRole);
    }

    public function test_admin_can_create_user_with_selected_permissions(): void
    {
        $usersView = Permission::where('name', 'users.view')->first();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Custom User',
            'email' => 'custom@test.com',
            'role' => 'member_org',
            'permissions' => [$usersView->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.permissions.0.id', $usersView->id);

        $user = User::where('email', 'custom@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasPermission('users.view'));
    }

    public function test_direct_permissions_are_authoritative_over_role_defaults(): void
    {
        // member_org role grants programmes.view by default.
        $usersView = Permission::where('name', 'users.view')->first();

        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/users', [
            'name' => 'Custom User',
            'email' => 'custom@test.com',
            'role' => 'member_org',
            'permissions' => [$usersView->id],
        ]);

        $user = User::where('email', 'custom@test.com')->first();

        // Exactly the selected ability applies — the role default is ignored.
        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertFalse($user->hasPermission('programmes.view'));
    }

    public function test_user_without_direct_permissions_keeps_role_defaults(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/users', [
            'name' => 'Plain Member',
            'email' => 'plain@test.com',
            'role' => 'member_org',
        ]);

        $user = User::where('email', 'plain@test.com')->first();
        $this->assertTrue($user->hasPermission('programmes.view'));
        $this->assertFalse($user->hasPermission('users.view'));
    }

    public function test_admin_can_update_user_permissions(): void
    {
        $usersView = Permission::where('name', 'users.view')->first();
        $orgView = Permission::where('name', 'organisations.view')->first();

        $user = User::create([
            'name' => 'Target User',
            'email' => 'target@test.com',
            'password' => bcrypt('password'),
            'role' => 'member_org',
            'status' => 'active',
        ]);
        $user->permissions()->sync([$usersView->id]);

        $this->actingAs($this->admin, 'sanctum');

        $this->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Target User Updated',
            'permissions' => [$orgView->id],
        ])->assertStatus(200)
            ->assertJsonPath('user.permissions.0.id', $orgView->id);

        $user->refresh();
        $this->assertTrue($user->hasPermission('organisations.view'));
        $this->assertFalse($user->hasPermission('users.view'));
    }

    public function test_empty_permissions_array_clears_direct_permissions(): void
    {
        $usersView = Permission::where('name', 'users.view')->first();

        $user = User::create([
            'name' => 'Target User',
            'email' => 'target@test.com',
            'password' => bcrypt('password'),
            'role' => 'member_org',
            'status' => 'active',
        ]);
        $user->permissions()->sync([$usersView->id]);

        $this->actingAs($this->admin, 'sanctum');

        $this->patchJson("/api/admin/users/{$user->id}", [
            'permissions' => [],
        ])->assertStatus(200);

        $user->refresh();
        $this->assertCount(0, $user->permissions);
        // Falls back to role-derived defaults again.
        $this->assertTrue($user->hasPermission('programmes.view'));
    }

    public function test_invalid_permission_id_is_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/users', [
            'name' => 'Bad User',
            'email' => 'bad@test.com',
            'role' => 'member_org',
            'permissions' => [999999],
        ])->assertStatus(422);
    }
}
