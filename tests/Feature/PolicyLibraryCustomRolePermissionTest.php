<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Policy Library used to be the last resource in the app gated entirely by
 * the legacy role:nep_admin,nep_coordinator,member_org / role:nep_admin
 * middleware — there was no way to grant a custom role any access to it at
 * all. Now permission:policy.view/create/update/delete, same shape as
 * every other resource: a custom role granted just the permission it needs
 * can use the feature, independent of the three built-in role names.
 */
class PolicyLibraryCustomRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function grantedUser(string $roleName, array $permissionNames): User
    {
        $role = Role::create(['name' => $roleName, 'display_name' => $roleName]);
        $role->permissions()->sync(
            collect($permissionNames)->map(
                fn ($name) => Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'group' => 'Policy Library'])->id
            )
        );

        $user = User::create([
            'name' => ucfirst($roleName), 'email' => "{$roleName}@test.com", 'password' => bcrypt('password'),
            'role' => $roleName, 'status' => 'active',
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_a_custom_role_granted_only_policy_view_can_list_documents_but_not_create_them(): void
    {
        $user = $this->grantedUser('policy_reader', ['policy.view']);

        $this->actingAs($user, 'sanctum')->getJson('/api/policy-documents')->assertStatus(200);

        $this->actingAs($user, 'sanctum')->postJson('/api/policy-documents', [
            'title' => 'Should be blocked', 'authority' => 'Test', 'version' => '1.0', 'date' => '2026-01-01',
        ])->assertStatus(403);
    }

    public function test_a_custom_role_granted_policy_create_can_add_a_document(): void
    {
        $user = $this->grantedUser('policy_author', ['policy.create']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/policy-documents', [
            'title' => 'New Policy', 'authority' => 'MoEYS', 'version' => '1.0', 'date' => '2026-01-01',
        ]);

        $response->assertStatus(201);
    }

    public function test_a_role_with_no_policy_permissions_at_all_is_forbidden_from_every_policy_route(): void
    {
        $user = $this->grantedUser('unrelated_role', ['dashboard.view']);
        $document = PolicyDocument::create([
            'title' => 'x', 'authority' => 'x', 'version' => '1.0', 'date' => '2026-01-01', 'created_by' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/policy-documents')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->postJson('/api/policy-documents', [])->assertStatus(403);
        $this->actingAs($user, 'sanctum')->patchJson("/api/policy-documents/{$document->id}", [])->assertStatus(403);
        $this->actingAs($user, 'sanctum')->deleteJson("/api/policy-documents/{$document->id}")->assertStatus(403);
    }

    public function test_a_custom_role_granted_only_policy_delete_can_delete_but_not_view(): void
    {
        // Deliberately narrow grant, to prove the four permissions are
        // fully independent of each other (not bundled).
        $user = $this->grantedUser('policy_archiver', ['policy.delete']);
        $document = PolicyDocument::create([
            'title' => 'x', 'authority' => 'x', 'version' => '1.0', 'date' => '2026-01-01', 'created_by' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/policy-documents')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->deleteJson("/api/policy-documents/{$document->id}")->assertStatus(200);
    }
}
