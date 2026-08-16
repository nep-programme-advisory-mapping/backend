<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * programmes.view-own (seeded to the new nep_staff role) restricts a
 * staff-like user — organisation_id null, so hasOrganisationWideAccess()
 * would otherwise show them every organisation's entries — to only the
 * programme entries they personally created. See
 * ScopesProgrammeEntryAccess::userCanAccessProgrammeEntry() and
 * ProgrammeEntryController::entriesByStatus()/index() for the actual rule,
 * and User::wantsOwnProgrammeEntriesOnly() for why it's not just a bare
 * hasPermission() check (that would also wrongly confine a super admin).
 */
class ProgrammeViewOwnPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_nep_staff_only_sees_entries_they_personally_created_in_listings(): void
    {
        $staff = User::factory()->create(['role' => 'nep_staff', 'organisation_id' => null]);
        $otherStaff = User::factory()->create(['role' => 'nep_staff', 'organisation_id' => null]);

        $ownEntry = ProgrammeEntry::factory()->create(['created_by' => $staff->id, 'is_submitted' => true]);
        $othersEntry = ProgrammeEntry::factory()->create(['created_by' => $otherStaff->id, 'is_submitted' => true]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/programme-entries');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownEntry->id));
        $this->assertFalse($ids->contains($othersEntry->id));
    }

    public function test_nep_staff_cannot_open_another_staff_members_entry_by_id(): void
    {
        $staff = User::factory()->create(['role' => 'nep_staff', 'organisation_id' => null]);
        $otherStaff = User::factory()->create(['role' => 'nep_staff', 'organisation_id' => null]);
        $othersEntry = ProgrammeEntry::factory()->create(['created_by' => $otherStaff->id]);

        $response = $this->actingAs($staff, 'sanctum')->getJson("/api/programme-entries/{$othersEntry->id}");

        $response->assertStatus(404);
    }

    public function test_nep_staff_can_open_their_own_entry_by_id(): void
    {
        $staff = User::factory()->create(['role' => 'nep_staff', 'organisation_id' => null]);
        $ownEntry = ProgrammeEntry::factory()->create(['created_by' => $staff->id]);

        $response = $this->actingAs($staff, 'sanctum')->getJson("/api/programme-entries/{$ownEntry->id}");

        $response->assertOk();
    }

    public function test_nep_staff_is_blocked_from_the_organisation_wide_browse_endpoint(): void
    {
        // 403, not 404: this route's middleware requires programmes.view,
        // which nep_staff doesn't hold (only programmes.view-own) — so it's
        // blocked before ever reaching ProgrammeEntryController::index()'s
        // own wantsOwnProgrammeEntriesOnly() check. That check still
        // matters for a hypothetical custom role granted BOTH permissions
        // at once (the admin UI allows any combination) — this test
        // documents the more common path for the seeded nep_staff role.
        $staff = User::factory()->create(['role' => 'nep_staff', 'organisation_id' => null]);
        $organisation = Organisation::factory()->create();

        $response = $this->actingAs($staff, 'sanctum')->getJson("/api/organisations/{$organisation->id}/programme-entries");

        $response->assertStatus(403);
    }

    public function test_a_role_with_both_view_and_view_own_is_still_blocked_from_the_organisation_wide_browse_endpoint(): void
    {
        $role = \App\Models\Role::create(['name' => 'hybrid_viewer', 'display_name' => 'Hybrid Viewer']);
        $role->permissions()->sync(
            \App\Models\Permission::whereIn('name', ['programmes.view', 'programmes.view-own'])->pluck('id')
        );
        $user = User::create([
            'name' => 'Hybrid', 'email' => 'hybrid@test.com', 'password' => bcrypt('password'),
            'role' => 'hybrid_viewer', 'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $organisation = Organisation::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/organisations/{$organisation->id}/programme-entries");

        // Passes the route's permission:programmes.view middleware this
        // time, so it's ProgrammeEntryController::index()'s own check that
        // must catch it.
        $response->assertStatus(404);
    }

    public function test_super_admin_is_never_confined_by_programmes_view_own_even_though_hasPermission_returns_true_for_it(): void
    {
        // Regression guard: hasPermission() intentionally returns true for a
        // super admin on any permission name, including this restricting
        // one — a naive `hasPermission('programmes.view-own')` check would
        // wrongly limit nep_admin to only self-created entries.
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $someoneElsesEntry = ProgrammeEntry::factory()->create(['is_submitted' => true]);

        $listResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/programme-entries');
        $listResponse->assertOk();
        $this->assertTrue(collect($listResponse->json('data'))->pluck('id')->contains($someoneElsesEntry->id));

        $showResponse = $this->actingAs($admin, 'sanctum')->getJson("/api/programme-entries/{$someoneElsesEntry->id}");
        $showResponse->assertOk();
    }

    public function test_nep_coordinator_is_unaffected_and_still_sees_every_organisations_entries(): void
    {
        // nep_coordinator doesn't hold programmes.view-own — confirms the
        // new permission is opt-in and didn't change the existing
        // organisation-wide default for roles that don't have it.
        $coordinator = User::factory()->create(['role' => 'nep_coordinator']);
        $entry = ProgrammeEntry::factory()->create(['is_submitted' => true]);

        $response = $this->actingAs($coordinator, 'sanctum')->getJson('/api/programme-entries');

        $response->assertOk();
        $this->assertTrue(collect($response->json('data'))->pluck('id')->contains($entry->id));
    }

    public function test_nep_staff_role_is_seeded_with_the_expected_permissions(): void
    {
        $staff = User::factory()->create(['role' => 'nep_staff']);

        $this->assertTrue($staff->hasPermission('programmes.view-own'));
        $this->assertTrue($staff->hasPermission('programmes.create'));
        $this->assertTrue($staff->hasPermission('programmes.update'));
        $this->assertTrue($staff->hasPermission('taxonomy.view'));
        $this->assertFalse($staff->hasPermission('programmes.view'));
        $this->assertFalse($staff->isSuperAdmin());
    }
}
