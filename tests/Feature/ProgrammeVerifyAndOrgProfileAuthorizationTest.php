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
 * Covers two gaps found while auditing programmes.verify and the
 * organisation self-service profile endpoints for hardcoded role checks:
 *   1. verify() previously had no ownership/scope check at all — harmless
 *      only because nobody but the super admin held programmes.verify, and
 *      became a real cross-organisation hole the moment a dynamic,
 *      organisation-bound role (like member_org) was granted it.
 *   2. /organisations/me now requires organisation-profile.view/update — a
 *      *different* permission from organisations.view/update, which is the
 *      unscoped "manage any organisation" admin capability. Granting the
 *      latter to an organisation-bound role by mistake would let them edit
 *      every organisation via /admin/organisations/{id}, not just their own.
 */
class ProgrammeVerifyAndOrgProfileAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    // --- programmes.verify: permission + scope ------------------------------

    public function test_member_org_can_verify_own_organisations_entry(): void
    {
        $org = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $org->id, 'role' => 'member_org']);
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);

        $response = $this->actingAs($user)->patchJson("/api/programme-entries/{$entry->id}/verify");

        $response->assertOk();
        $entry->refresh();
        $this->assertNotNull($entry->verified_date);
    }

    public function test_member_org_cannot_verify_another_organisations_entry(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $ownOrg->id, 'role' => 'member_org']);
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $otherOrg->id]);

        $response = $this->actingAs($user)->patchJson("/api/programme-entries/{$entry->id}/verify");

        $response->assertForbidden();
        $entry->refresh();
        $this->assertNull($entry->verified_date);
    }

    public function test_a_user_without_programmes_verify_permission_gets_403(): void
    {
        $org = Organisation::factory()->create();
        // A custom role with view/update but deliberately no verify —
        // proves this is permission-driven, not a role-name check.
        $role = Role::create(['name' => 'no_verify_role', 'display_name' => 'No Verify', 'is_system' => false, 'is_super_admin' => false]);
        $role->permissions()->sync(\App\Models\Permission::whereIn('name', ['programmes.view', 'programmes.update'])->pluck('id'));
        $user = User::factory()->create(['organisation_id' => $org->id, 'role' => 'no_verify_role']);
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);

        $response = $this->actingAs($user)->patchJson("/api/programme-entries/{$entry->id}/verify");

        $response->assertForbidden();
    }

    public function test_admin_can_verify_any_organisations_entry(): void
    {
        $admin = User::factory()->create(['organisation_id' => null, 'role' => 'nep_admin']);
        $entry = ProgrammeEntry::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/programme-entries/{$entry->id}/verify");

        $response->assertOk();
    }

    public function test_a_dynamically_created_role_granted_verify_can_verify_within_scope(): void
    {
        // No developer changes required for a brand-new admin-created role —
        // the exact "Admin creates Programme Reviewer, grants
        // programmes.verify, assigns to a user" scenario from the request.
        $org = Organisation::factory()->create();
        $reviewerRole = Role::create(['name' => 'programme_reviewer', 'display_name' => 'Programme Reviewer', 'is_system' => false, 'is_super_admin' => false]);
        $reviewerRole->permissions()->sync(\App\Models\Permission::whereIn('name', ['programmes.view', 'programmes.verify'])->pluck('id'));
        $reviewer = User::factory()->create(['organisation_id' => $org->id, 'role' => 'programme_reviewer']);
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);

        $response = $this->actingAs($reviewer)->patchJson("/api/programme-entries/{$entry->id}/verify");

        $response->assertOk();
    }

    // --- organisation-profile.view/update ------------------------------------

    public function test_member_org_can_view_and_update_their_own_organisation_profile(): void
    {
        $org = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $org->id, 'role' => 'member_org']);

        $this->actingAs($user)->getJson('/api/organisations/me')->assertOk();

        $response = $this->actingAs($user)->patchJson('/api/organisations/me', ['contact_name' => 'New Contact']);
        $response->assertOk();
        $this->assertDatabaseHas('organisations', ['id' => $org->id, 'contact_name' => 'New Contact']);
    }

    public function test_a_role_without_organisation_profile_permission_gets_403(): void
    {
        $org = Organisation::factory()->create();
        $role = Role::create(['name' => 'no_org_profile_role', 'display_name' => 'No Org Profile', 'is_system' => false, 'is_super_admin' => false]);
        $role->permissions()->sync(\App\Models\Permission::whereIn('name', ['programmes.view'])->pluck('id'));
        $user = User::factory()->create(['organisation_id' => $org->id, 'role' => 'no_org_profile_role']);

        $this->actingAs($user)->getJson('/api/organisations/me')->assertForbidden();
        $this->actingAs($user)->patchJson('/api/organisations/me', ['contact_name' => 'x'])->assertForbidden();
    }

    public function test_organisation_profile_permission_does_not_grant_access_to_admin_organisation_routes(): void
    {
        // organisation-profile.update is deliberately a different permission
        // from organisations.update (the unscoped admin "manage any
        // organisation" capability) — holding the former must not
        // incidentally unlock /admin/organisations/{id} for every org.
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $ownOrg->id, 'role' => 'member_org']);

        $response = $this->actingAs($user)->putJson("/api/admin/organisations/{$otherOrg->id}", ['name' => 'Hijacked']);

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_organisation_profile(): void
    {
        $this->getJson('/api/organisations/me')->assertUnauthorized();
    }
}
