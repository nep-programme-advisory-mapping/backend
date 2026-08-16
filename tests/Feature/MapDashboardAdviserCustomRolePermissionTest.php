<?php

namespace Tests\Feature;

use App\Models\AdvisoryNote;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\ProgrammeEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 goal for the Map/Export/Dashboard and Adviser modules: a custom
 * role granted the right permission can use the feature, without any of the
 * previous hardcoded role:nep_admin,nep_coordinator gating.
 */
class MapDashboardAdviserCustomRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function grantedUser(string $roleName, array $permissionNames, ?int $organisationId = null): User
    {
        $role = Role::create(['name' => $roleName, 'display_name' => $roleName]);
        $role->permissions()->sync(
            collect($permissionNames)->map(
                fn ($name) => Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'group' => explode('.', $name)[0]])->id
            )
        );

        $user = User::create([
            'name' => ucfirst($roleName), 'email' => "{$roleName}@test.com", 'password' => bcrypt('password'),
            'role' => $roleName, 'status' => 'active', 'organisation_id' => $organisationId,
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_a_custom_role_granted_dashboard_view_can_see_dashboard_stats(): void
    {
        $user = $this->grantedUser('data_analyst', ['dashboard.view']);

        $this->actingAs($user, 'sanctum')->getJson('/api/dashboard/stats')->assertStatus(200);
    }

    public function test_a_role_without_dashboard_view_is_forbidden(): void
    {
        $user = $this->grantedUser('no_dashboard', []);

        $this->actingAs($user, 'sanctum')->getJson('/api/dashboard/stats')->assertStatus(403);
    }

    public function test_a_custom_org_bound_role_with_reports_view_sees_only_its_own_org_on_the_map(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $ownEntry = ProgrammeEntry::factory()->create(['organisation_id' => $ownOrg->id, 'is_submitted' => true]);
        ProgrammeEntry::factory()->create(['organisation_id' => $otherOrg->id, 'is_submitted' => true]);

        $user = $this->grantedUser('field_officer', ['reports.view'], $ownOrg->id);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/map/entries');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownEntry->id);
    }

    public function test_a_custom_staff_like_role_with_reports_view_sees_the_whole_map(): void
    {
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();
        ProgrammeEntry::factory()->create(['organisation_id' => $orgA->id, 'is_submitted' => true]);
        ProgrammeEntry::factory()->create(['organisation_id' => $orgB->id, 'is_submitted' => true]);

        $user = $this->grantedUser('oversight_analyst', ['reports.view'], null);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/map/entries');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_a_custom_role_granted_advisory_view_all_can_list_submissions(): void
    {
        AdvisoryNote::factory()->create();
        $user = $this->grantedUser('adviser_ops', ['advisory.view-all']);

        $this->actingAs($user, 'sanctum')->getJson('/api/adviser/submissions')->assertStatus(200);
    }

    public function test_advisory_view_alone_does_not_unlock_the_submissions_list(): void
    {
        // advisory.view (own delivered note) and advisory.view-all (browse
        // every submission) are deliberately distinct — see RolePermissionSeeder.
        AdvisoryNote::factory()->create();
        $user = $this->grantedUser('narrow_viewer', ['advisory.view']);

        $this->actingAs($user, 'sanctum')->getJson('/api/adviser/submissions')->assertStatus(403);
    }

    public function test_a_custom_role_granted_advisory_create_can_submit_a_document(): void
    {
        $user = $this->grantedUser('intake_officer', ['advisory.create']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/adviser/submissions', [
            'submitting_party' => 'Ministry of Education',
            'document_name' => 'Annual Review',
        ]);

        $response->assertStatus(201);
    }

    public function test_a_custom_role_granted_advisory_deliver_can_mark_delivered(): void
    {
        $note = AdvisoryNote::factory()->create(['status' => 'analysed']);
        $user = $this->grantedUser('delivery_officer', ['advisory.deliver']);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/adviser/submissions/{$note->id}/deliver")
            ->assertStatus(200);
    }

    public function test_advisory_create_alone_cannot_deliver(): void
    {
        $note = AdvisoryNote::factory()->create(['status' => 'analysed']);
        $user = $this->grantedUser('intake_only', ['advisory.create']);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/adviser/submissions/{$note->id}/deliver")
            ->assertStatus(403);
    }
}
