<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Permission;
use App\Models\ProgrammeEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for BUG-08: ProgrammeEntryController::verify() and
 * TaxonomyController's create/rename/deprecate methods used to hardcode
 * role === 'nep_admin' underneath their (already permission-gated) routes,
 * silently defeating a permission grant made to any other role. Both dropped
 * the redundant inline check — this confirms a custom role granted the
 * relevant permission can now actually use the feature.
 */
class PermissionGrantsWorkOnPreviouslyDoubleGatedRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_custom_role_granted_programmes_verify_can_verify_an_entry(): void
    {
        $role = Role::create(['name' => 'reviewer', 'display_name' => 'Reviewer']);
        $role->permissions()->sync(
            [Permission::create(['name' => 'programmes.verify', 'display_name' => 'Verify Programmes', 'group' => 'Programmes'])->id]
        );

        $user = User::create([
            'name' => 'Reviewer', 'email' => 'reviewer@test.com', 'password' => bcrypt('password'),
            'role' => 'reviewer', 'status' => 'active',
        ]);
        $user->roles()->attach($role);

        $org = Organisation::factory()->create();
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $org->id, 'verified_date' => null]);

        $response = $this->actingAs($user, 'sanctum')->patchJson("/api/programme-entries/{$entry->id}/verify");

        $response->assertStatus(200);
        $this->assertNotNull($entry->fresh()->verified_date);
    }

    public function test_a_custom_role_without_programmes_verify_is_still_forbidden(): void
    {
        $role = Role::create(['name' => 'no_verify', 'display_name' => 'No Verify']);
        $user = User::create([
            'name' => 'Nobody', 'email' => 'noverify@test.com', 'password' => bcrypt('password'),
            'role' => 'no_verify', 'status' => 'active',
        ]);
        $user->roles()->attach($role);

        $org = Organisation::factory()->create();
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);

        $response = $this->actingAs($user, 'sanctum')->patchJson("/api/programme-entries/{$entry->id}/verify");

        $response->assertStatus(403);
    }

    public function test_a_custom_role_granted_taxonomy_create_can_create_a_taxonomy_category(): void
    {
        $role = Role::create(['name' => 'taxonomist', 'display_name' => 'Taxonomist']);
        $role->permissions()->sync(
            [Permission::create(['name' => 'taxonomy.create', 'display_name' => 'Create Taxonomy', 'group' => 'Taxonomy'])->id]
        );

        $user = User::create([
            'name' => 'Taxonomist', 'email' => 'taxonomist@test.com', 'password' => bcrypt('password'),
            'role' => 'taxonomist', 'status' => 'active',
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/taxonomy/categories', [
            'code' => 'health',
            'label' => 'Health',
        ]);

        $response->assertStatus(201);
    }

    public function test_taxonomy_view_alone_does_not_unlock_the_other_entries_review_queue(): void
    {
        // /taxonomy/other-entries is now gated by its own dedicated
        // taxonomy.review-other permission (not taxonomy.view, which is
        // shared far more broadly — e.g. by member_org, for the taxonomy
        // pickers on the entry form). Holding taxonomy.view alone must not
        // unlock this curation queue.
        $memberRole = Role::create(['name' => 'member_org', 'display_name' => 'Member Organisation']);
        $memberRole->permissions()->sync(
            [Permission::create(['name' => 'taxonomy.view', 'display_name' => 'View Taxonomy', 'group' => 'Taxonomy'])->id]
        );

        $member = User::create([
            'name' => 'Member', 'email' => 'member@test.com', 'password' => bcrypt('password'),
            'role' => 'member_org', 'status' => 'active',
        ]);
        $member->roles()->attach($memberRole);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/taxonomy/other-entries');

        $response->assertStatus(403);
    }

    public function test_a_custom_role_granted_taxonomy_review_other_can_access_the_queue(): void
    {
        $role = Role::create(['name' => 'taxonomy_curator', 'display_name' => 'Taxonomy Curator']);
        $role->permissions()->sync(
            [Permission::create(['name' => 'taxonomy.review-other', 'display_name' => 'Review Other Entries', 'group' => 'Taxonomy'])->id]
        );

        $user = User::create([
            'name' => 'Curator', 'email' => 'curator@test.com', 'password' => bcrypt('password'),
            'role' => 'taxonomy_curator', 'status' => 'active',
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/taxonomy/other-entries');

        $response->assertStatus(200);
    }
}
