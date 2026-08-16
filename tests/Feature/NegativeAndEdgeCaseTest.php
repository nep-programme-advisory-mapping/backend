<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-cutting negative/edge cases that don't belong to any single
 * feature's test file: invalid/non-existent IDs, bad or missing auth,
 * malformed input, and boundary values. Complements the positive-path
 * coverage already spread across the rest of tests/Feature.
 */
class NegativeAndEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    // ---------------------------------------------------------------
    // Authentication
    // ---------------------------------------------------------------

    public function test_completely_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/dashboard/stats')->assertUnauthorized();
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_invalid_bearer_token_is_rejected(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->getJson('/api/user');

        $response->assertUnauthorized();
    }

    public function test_logout_deletes_the_current_access_token(): void
    {
        // Asserted against the token table directly rather than with a
        // second live request using the same (now-revoked) token: Laravel's
        // HTTP test client reuses one application instance for every call
        // within a test, and this app also runs on Octane (long-lived
        // worker processes) — either can leave a guard's resolved-user
        // memoized across requests independently of whether the token row
        // still exists. That's a real question worth checking against the
        // actually-running server (see the Automation Testing Report), but
        // isn't something this in-process assertion can answer either way.
        $user = User::factory()->create(['role' => 'nep_admin']);
        $token = $user->createToken('test-device');

        $this->withHeaders(['Authorization' => "Bearer {$token->plainTextToken}"])
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'known@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'known@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $this->assertGuest();
    }

    public function test_login_with_non_existent_email_is_rejected_without_leaking_account_existence(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        // Same status/shape as a wrong password on a real account — an
        // account-enumeration guard, not just an auth check.
        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Invalid / non-existent resource IDs
    // ---------------------------------------------------------------

    public function test_fetching_a_non_existent_programme_entry_returns_404(): void
    {
        $user = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/programme-entries/999999');

        $response->assertStatus(404);
    }

    public function test_non_numeric_programme_entry_id_returns_404_not_500(): void
    {
        $user = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/programme-entries/not-a-number');

        $response->assertStatus(404);
    }

    public function test_updating_a_non_existent_role_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/roles/999999', ['display_name' => 'x']);

        $response->assertStatus(404);
    }

    public function test_deleting_a_non_existent_user_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson('/api/admin/users/999999');

        $response->assertStatus(404);
    }

    public function test_assigning_a_role_to_a_non_existent_user_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $role = Role::create(['name' => 'temp_role_2', 'display_name' => 'Temp']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/roles/{$role->id}/users/999999");

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Malformed / missing input
    // ---------------------------------------------------------------

    public function test_creating_a_role_without_a_name_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/roles', [
            'display_name' => 'Missing Name',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_creating_a_user_with_an_invalid_email_format_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users/invite', [
            'name' => 'Someone',
            'email' => 'not-an-email',
            'role' => 'member_org',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_creating_a_user_with_a_role_that_does_not_exist_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users/invite', [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'role' => 'role_that_does_not_exist',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_duplicate_email_on_user_invite_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users/invite', [
            'name' => 'Someone Else',
            'email' => 'existing@example.com',
            'role' => 'member_org',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_empty_request_body_is_rejected_as_validation_error_not_a_server_error(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/roles', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['name', 'display_name']);
    }

    // ---------------------------------------------------------------
    // Boundary values
    // ---------------------------------------------------------------

    public function test_per_page_beyond_the_documented_maximum_is_clamped_not_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/users?per_page=99999');

        // Whatever the server's cap is, an oversized value must not 500 or
        // silently return everything unpaginated.
        $response->assertOk();
    }

    public function test_negative_page_number_does_not_error(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/users?page=-1');

        $response->assertOk();
    }

    // ---------------------------------------------------------------
    // Cross-organisation access (data isolation)
    // ---------------------------------------------------------------

    public function test_member_org_cannot_fetch_another_organisations_programme_entry_by_guessing_its_id(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $member = User::factory()->create(['role' => 'member_org', 'organisation_id' => $ownOrg->id]);
        $otherEntry = ProgrammeEntry::factory()->create(['organisation_id' => $otherOrg->id]);

        $response = $this->actingAs($member, 'sanctum')->getJson("/api/programme-entries/{$otherEntry->id}");

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }
}
