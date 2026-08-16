<?php

namespace Tests\Feature\Admin;

use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests reference role names ('member_org', 'nep_coordinator')
        // as request payload values, not just factory-created users — seed
        // the real roles table so the dynamic `exists:roles,name` validation
        // has something to validate against, same as production always does.
        $this->seed(RolePermissionSeeder::class);
    }

    // ── AC #1: Only NEP Admin can create/edit/deactivate accounts ──────

    public function test_nep_admin_can_create_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin', 'status' => 'active']);
        $org = Organisation::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'organisation_id' => $org->id,
            'name' => 'Test Member',
            'email' => 'admin@example.com',
            'role' => 'member_org',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.email', 'admin@example.com');
        $this->assertNotNull($response->json('temporary_password'));
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
    }

    public function test_nep_coordinator_cannot_create_a_user(): void
    {
        $coordinator = User::factory()->create(['role' => 'nep_coordinator', 'status' => 'active']);

        $response = $this->actingAs($coordinator, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'Nope',
            'email' => 'coordinator@example.com',
            'role' => 'member_org',
        ]);

        $response->assertForbidden();
    }

    public function test_member_org_cannot_create_a_user(): void
    {
        $member = User::factory()->create(['role' => 'member_org', 'status' => 'active']);

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'Nope',
            'email' => 'orgadmin@example.com',
            'role' => 'member_org',
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_is_blocked(): void
    {
        $this->postJson('/api/admin/users', [
            'name' => 'Nope',
            'email' => 'orgadmin@example.com',
            'role' => 'member_org',
        ])->assertUnauthorized();
    }

    public function test_nep_admin_can_edit_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'member_org', 'status' => 'active']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
    }

    // ── AC #2: Deactivated accounts cannot log in ───────────────────────

    public function test_editing_a_user_to_inactive_revokes_their_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'member_org', 'status' => 'active']);
        $user->createToken('test-token');

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$user->id}", [
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_deactivating_a_user_revokes_their_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'member_org', 'status' => 'active']);
        $user->createToken('test-token');

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/users/{$user->id}/deactivate");

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'role' => 'member_org',
            'status' => 'inactive',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // AuthController::login() checks status after Auth::attempt() succeeds
        // and returns 403 before a token is issued.
        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Account is deactivated.');
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin', 'status' => 'active']);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/users/{$admin->id}/deactivate");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'status' => 'active']);
    }

    // ── AC #3: Admin can reset a member org's credentials independently ──

    public function test_resetting_credentials_revokes_old_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member_org', 'status' => 'active']);
        $member->createToken('old-token');
        $originalHash = $member->password;

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/users/{$member->id}/reset-credentials");

        $response->assertOk();
        $this->assertNotNull($response->json('temporary_password'));
        $member->refresh();
        $this->assertNotSame($originalHash, $member->password);
        $this->assertSame(0, $member->tokens()->count());
    }

    public function test_member_org_can_log_in_with_reset_temporary_password(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'member_org', 'status' => 'active']);

        $reset = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$member->id}/reset-credentials")
            ->json();

        Auth::forgetGuards();

        $login = $this->postJson('/api/login', [
            'email' => $member->email,
            'password' => $reset['temporary_password'],
        ]);

        $login->assertOk();
        $login->assertJsonPath('user.email', $member->email);
        $this->assertNotNull($login->json('token'));
    }
}
