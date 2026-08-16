<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers dynamic role assignment on users (create-time and after-the-fact),
 * and the self-protection / privilege-escalation guards.
 */
class UserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::create([
            'name' => 'nep_admin', 'display_name' => 'NEP Administrator', 'is_system' => true, 'is_super_admin' => true,
        ]);

        Role::create(['name' => 'member_org', 'display_name' => 'Member Organisation', 'is_system' => true]);

        foreach ([
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'Users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'Users'],
            ['name' => 'users.update', 'display_name' => 'Update Users', 'group' => 'Users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'group' => 'Users'],
        ] as $permission) {
            Permission::create($permission);
        }

        $this->superAdmin = User::create([
            'name' => 'Super Admin', 'email' => 'super@test.com', 'password' => bcrypt('password'),
            'role' => 'nep_admin', 'status' => 'active',
        ]);
        $this->superAdmin->roles()->attach($superAdminRole);
    }

    public function test_admin_can_create_a_user_with_a_brand_new_dynamic_role(): void
    {
        $role = Role::create(['name' => 'programme_manager', 'display_name' => 'Programme Manager']);

        $this->actingAs($this->superAdmin, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'programme_manager',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.role', 'programme_manager');

        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue($user->roles->contains($role));
    }

    public function test_admin_can_change_an_existing_users_role_after_creation(): void
    {
        $memberRole = Role::where('name', 'member_org')->first();
        $managerRole = Role::create(['name' => 'programme_manager', 'display_name' => 'Programme Manager']);

        $user = User::create([
            'name' => 'Target', 'email' => 'target@test.com', 'password' => bcrypt('password'),
            'role' => 'member_org', 'status' => 'active',
        ]);
        $user->roles()->attach($memberRole);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/users/{$user->id}", ['role' => 'programme_manager'])
            ->assertStatus(200)
            ->assertJsonPath('user.role', 'programme_manager');

        $user->refresh();
        $this->assertEquals('programme_manager', $user->role);
    }

    public function test_user_created_with_a_role_receives_that_roles_permissions(): void
    {
        $managerRole = Role::create(['name' => 'programme_manager', 'display_name' => 'Programme Manager']);
        $managerRole->permissions()->sync(Permission::where('name', 'users.view')->pluck('id'));

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->postJson('/api/admin/users', [
            'name' => 'Manager', 'email' => 'manager@test.com', 'role' => 'programme_manager',
        ]);

        $user = User::where('email', 'manager@test.com')->first();
        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertFalse($user->hasPermission('users.delete'));
    }

    public function test_role_field_rejects_a_role_name_that_does_not_exist(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->postJson('/api/admin/users', [
            'name' => 'Bad', 'email' => 'bad@test.com', 'role' => 'not_a_real_role',
        ])->assertStatus(422);
    }

    // ─── Self-protection ────────────────────────────────────────────────────

    public function test_admin_cannot_change_their_own_role(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $response = $this->patchJson("/api/admin/users/{$this->superAdmin->id}", [
            'role' => 'member_org',
        ]);

        $response->assertStatus(422);
        $this->assertEquals('nep_admin', $this->superAdmin->fresh()->role);
    }

    public function test_admin_cannot_change_their_own_status(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/users/{$this->superAdmin->id}", [
            'status' => 'inactive',
        ])->assertStatus(422);
    }

    public function test_admin_can_still_edit_their_own_profile_fields(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/users/{$this->superAdmin->id}", [
            'name' => 'Renamed Admin',
        ])->assertStatus(200);

        $this->assertEquals('Renamed Admin', $this->superAdmin->fresh()->name);
    }

    // ─── Privilege escalation ───────────────────────────────────────────────

    public function test_non_super_admin_cannot_assign_a_super_admin_role_to_a_user(): void
    {
        $limitedRole = Role::create(['name' => 'limited_admin', 'display_name' => 'Limited Admin']);
        $limitedRole->permissions()->sync(Permission::whereIn('name', ['users.create', 'users.update'])->pluck('id'));

        $limitedAdmin = User::create([
            'name' => 'Limited', 'email' => 'limited@test.com', 'password' => bcrypt('password'),
            'role' => 'limited_admin', 'status' => 'active',
        ]);
        $limitedAdmin->roles()->attach($limitedRole);

        $this->actingAs($limitedAdmin, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New Super', 'email' => 'newsuper@test.com', 'role' => 'nep_admin',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'newsuper@test.com']);
    }

    public function test_non_super_admin_cannot_grant_a_permission_they_do_not_hold(): void
    {
        $usersDelete = Permission::where('name', 'users.delete')->first();

        $limitedRole = Role::create(['name' => 'limited_admin', 'display_name' => 'Limited Admin']);
        $limitedRole->permissions()->sync(Permission::whereIn('name', ['users.create'])->pluck('id'));

        $limitedAdmin = User::create([
            'name' => 'Limited', 'email' => 'limited@test.com', 'password' => bcrypt('password'),
            'role' => 'limited_admin', 'status' => 'active',
        ]);
        $limitedAdmin->roles()->attach($limitedRole);

        $this->actingAs($limitedAdmin, 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User', 'email' => 'newuser@test.com', 'role' => 'member_org',
            'permissions' => [$usersDelete->id],
        ]);

        $response->assertStatus(403);
    }

    // ─── Deletion ───────────────────────────────────────────────────────────

    public function test_admin_can_delete_a_user_with_no_dependent_records(): void
    {
        $user = User::create([
            'name' => 'Deletable', 'email' => 'deletable@test.com', 'password' => bcrypt('password'),
            'role' => 'member_org', 'status' => 'active',
        ]);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->deleteJson("/api/admin/users/{$user->id}")->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * Regression test for BUG-01: advisory_notes.coordinator_id used to
     * cascade-delete, so deleting a coordinator silently destroyed every
     * advisory note assigned to them. It now detaches (sets null) instead.
     */
    public function test_deleting_a_coordinator_detaches_their_advisory_notes_instead_of_deleting_them(): void
    {
        $coordinator = User::create([
            'name' => 'Coordinator', 'email' => 'coordinator@test.com', 'password' => bcrypt('password'),
            'role' => 'nep_coordinator', 'status' => 'active',
        ]);

        $note = \App\Models\AdvisoryNote::create([
            'coordinator_id' => $coordinator->id,
            'submitting_party' => 'Ministry of Education',
            'document_name' => 'Sector Review',
            'analysis_scope' => 'full map',
            'status' => 'advice_delivered',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->deleteJson("/api/admin/users/{$coordinator->id}")->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $coordinator->id]);
        $this->assertDatabaseHas('advisory_notes', ['id' => $note->id, 'coordinator_id' => null]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->deleteJson("/api/admin/users/{$this->superAdmin->id}")->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
    }

    public function test_last_super_admin_cannot_be_deleted_even_by_another_privileged_user(): void
    {
        // A second, non-super-admin user holding users.delete tries to
        // remove the sole remaining super admin — must be blocked, since
        // that would leave the system without a super admin.
        $deleterRole = Role::create(['name' => 'deleter', 'display_name' => 'Deleter']);
        $deleterRole->permissions()->sync(Permission::where('name', 'users.delete')->pluck('id'));
        $deleter = User::create([
            'name' => 'Deleter', 'email' => 'deleter@test.com', 'password' => bcrypt('password'),
            'role' => 'deleter', 'status' => 'active',
        ]);
        $deleter->roles()->attach($deleterRole);

        $this->actingAs($deleter, 'sanctum');
        $response = $this->deleteJson("/api/admin/users/{$this->superAdmin->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
    }

    public function test_a_super_admin_can_be_deleted_when_another_one_remains(): void
    {
        $otherSuperAdmin = User::create([
            'name' => 'Other Super Admin', 'email' => 'other-super@test.com', 'password' => bcrypt('password'),
            'role' => 'nep_admin', 'status' => 'active',
        ]);
        $otherSuperAdmin->roles()->attach(Role::where('name', 'nep_admin')->first());

        $this->actingAs($otherSuperAdmin, 'sanctum');
        $this->deleteJson("/api/admin/users/{$this->superAdmin->id}")->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $this->superAdmin->id]);
    }

    // ─── 403 status codes ───────────────────────────────────────────────────

    public function test_user_without_permission_receives_403_not_500_or_200(): void
    {
        $memberRole = Role::where('name', 'member_org')->first();
        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.com', 'password' => bcrypt('password'),
            'role' => 'member_org', 'status' => 'active',
        ]);
        $member->roles()->attach($memberRole);

        $response = $this->actingAs($member, 'sanctum')->deleteJson('/api/admin/users/' . $this->superAdmin->id);

        $response->assertStatus(403);
    }
}
