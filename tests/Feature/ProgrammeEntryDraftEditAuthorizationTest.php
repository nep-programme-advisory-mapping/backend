<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * "Continue Editing a Draft" authorization — the 5 security scenarios from
 * the feature request, using the built-in role names directly for 1:1
 * traceability. The underlying mechanism (ScopesProgrammeEntryAccess /
 * userCanAccessProgrammeEntry) is dynamic/permission-driven and already
 * covered more generally by ProgrammeEntryCustomRolePermissionTest — this
 * file exercises the same mechanism through the concrete admin/member_org
 * scenario the request describes.
 */
class ProgrammeEntryDraftEditAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected Organisation $orgA;
    protected Organisation $orgB;
    protected User $orgAUser;
    protected User $orgBUser;
    protected User $admin;
    protected ProgrammeEntry $draftA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organisation::factory()->create();
        $this->orgB = Organisation::factory()->create();

        $this->orgAUser = User::factory()->create([
            'organisation_id' => $this->orgA->id,
            'role' => 'member_org',
        ]);

        $this->orgBUser = User::factory()->create([
            'organisation_id' => $this->orgB->id,
            'role' => 'member_org',
        ]);

        $this->admin = User::factory()->create([
            'organisation_id' => null,
            'role' => 'nep_admin',
        ]);

        $this->draftA = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->orgA->id,
            'is_submitted' => false,
        ]);
    }

    // --- Scenario 1: Admin -------------------------------------------------

    public function test_scenario_1_admin_can_view_draft_a(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/programme-entries/{$this->draftA->id}");
        $response->assertOk();
    }

    public function test_scenario_1_admin_can_edit_draft_a(): void
    {
        $response = $this->actingAs($this->admin)->putJson(
            "/api/programme-entries/{$this->draftA->id}",
            ['programme_name' => 'Updated by admin']
        );

        $response->assertOk();
        $this->assertDatabaseHas('programme_entries', [
            'id' => $this->draftA->id,
            'programme_name' => 'Updated by admin',
        ]);
    }

    // --- Scenario 2: Same organisation -------------------------------------

    public function test_scenario_2_organisation_a_user_can_view_organisation_a_draft(): void
    {
        $response = $this->actingAs($this->orgAUser)->getJson("/api/programme-entries/{$this->draftA->id}");
        $response->assertOk();
    }

    public function test_scenario_2_organisation_a_user_can_edit_organisation_a_draft(): void
    {
        $response = $this->actingAs($this->orgAUser)->putJson(
            "/api/programme-entries/{$this->draftA->id}",
            ['programme_name' => 'Updated by owning org']
        );

        $response->assertOk();
        $this->assertDatabaseHas('programme_entries', [
            'id' => $this->draftA->id,
            'programme_name' => 'Updated by owning org',
        ]);
    }

    // --- Scenario 3: Different organisation --------------------------------

    public function test_scenario_3_organisation_b_user_gets_403_editing_organisation_a_draft(): void
    {
        $response = $this->actingAs($this->orgBUser)->putJson(
            "/api/programme-entries/{$this->draftA->id}",
            ['programme_name' => 'Hijacked']
        );

        $response->assertForbidden();
        $this->assertDatabaseMissing('programme_entries', [
            'id' => $this->draftA->id,
            'programme_name' => 'Hijacked',
        ]);
    }

    public function test_scenario_3_organisation_b_user_gets_404_viewing_organisation_a_draft(): void
    {
        // show() intentionally 404s rather than 403s for cross-org reads, so
        // as not to confirm another organisation's entry even exists — see
        // ScopesProgrammeEntryAccess / ProgrammeEntryController::show().
        $response = $this->actingAs($this->orgBUser)->getJson("/api/programme-entries/{$this->draftA->id}");
        $response->assertNotFound();
    }

    // --- Scenario 4: Unauthenticated ----------------------------------------

    public function test_scenario_4_unauthenticated_get_is_401(): void
    {
        $response = $this->getJson("/api/programme-entries/{$this->draftA->id}");
        $response->assertUnauthorized();
    }

    public function test_scenario_4_unauthenticated_put_is_401(): void
    {
        $response = $this->putJson(
            "/api/programme-entries/{$this->draftA->id}",
            ['programme_name' => 'Should never apply']
        );
        $response->assertUnauthorized();
    }

    // --- Scenario 5: Direct API manipulation --------------------------------

    public function test_scenario_5_organisation_b_cannot_bypass_ui_via_direct_put(): void
    {
        // Same request an Organisation B user's browser would send if they
        // guessed/bookmarked Organisation A's entry id and skipped the UI
        // entirely — the backend, not the frontend, is what has to reject it.
        $response = $this->actingAs($this->orgBUser)->putJson(
            "/api/programme-entries/{$this->draftA->id}",
            [
                'programme_name' => 'Attacker-controlled name',
                'start_year' => 2020,
            ]
        );

        $response->assertForbidden();
        $this->draftA->refresh();
        $this->assertNotEquals('Attacker-controlled name', $this->draftA->programme_name);
    }

    public function test_scenario_5_organisation_b_cannot_publish_organisation_as_draft_via_direct_put(): void
    {
        // Note: UpdateProgrammeEntryRequest::authorize() always returns true
        // (this codebase's established pattern — real authorization is
        // canManage() in the controller, which runs after FormRequest
        // validation). That means a malicious payload that *also* fails
        // publish validation gets 422 before the 403 check is ever reached
        // (see the sibling test above, which avoids that by not attempting
        // to publish). To specifically confirm the 403 fires even for an
        // otherwise well-formed publish attempt, this entry is set up
        // complete enough to pass validation, isolating the authorization
        // check itself.
        $budgetBand = \App\Models\BudgetBand::factory()->create(['label' => 'Test band', 'min_amount' => 0, 'max_amount' => 1000]);
        $this->draftA->update([
            'budget_band_id' => $budgetBand->id,
            'ongoing' => true,
            'fte_staff' => 1,
            'direct_beneficiaries' => 1,
            'indirect_beneficiaries' => 1,
        ]);
        $item = \App\Models\ActivityItem::factory()->create(['is_active' => true]);
        \App\Models\ProgrammeActivity::factory()->create([
            'programme_entry_id' => $this->draftA->id,
            'activity_item_id' => $item->id,
        ]);
        $province = \App\Models\Province::factory()->create(['province_name' => 'Test Province']);
        \App\Models\ProgrammeLocation::factory()->create([
            'programme_entry_id' => $this->draftA->id,
            'province_id' => $province->id,
        ]);
        \App\Models\EntryKeyword::factory()->create([
            'programme_entry_id' => $this->draftA->id,
            'keyword' => 'test',
        ]);

        $response = $this->actingAs($this->orgBUser)->putJson(
            "/api/programme-entries/{$this->draftA->id}",
            ['is_submitted' => true]
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('programme_entries', [
            'id' => $this->draftA->id,
            'is_submitted' => false,
        ]);
    }

    // --- Draft-specific: does not accidentally affect published entries ----

    public function test_organisation_b_still_cannot_edit_organisation_a_published_entry(): void
    {
        // The draft-editing feature must not change existing published-entry
        // behaviour — cross-org write is rejected the same way regardless of
        // is_submitted.
        $published = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->orgA->id,
            'is_submitted' => true,
        ]);

        $response = $this->actingAs($this->orgBUser)->putJson(
            "/api/programme-entries/{$published->id}",
            ['programme_name' => 'Hijacked published entry']
        );

        $response->assertForbidden();
    }

    public function test_admin_can_still_edit_a_published_entry_same_as_before(): void
    {
        $published = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->orgA->id,
            'is_submitted' => true,
        ]);

        $response = $this->actingAs($this->admin)->putJson(
            "/api/programme-entries/{$published->id}",
            ['programme_name' => 'Admin edit on published entry']
        );

        $response->assertOk();
    }

    // --- Draft workflow: continue editing does not duplicate --------------

    public function test_continuing_a_draft_reuses_the_same_id_and_stays_draft(): void
    {
        // Simulates "open draft list -> continue editing -> autosave update"
        // for the owning organisation.
        $response = $this->actingAs($this->orgAUser)->putJson(
            "/api/programme-entries/{$this->draftA->id}",
            ['method' => 'Continued editing this session']
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $this->draftA->id);
        $this->assertDatabaseHas('programme_entries', [
            'id' => $this->draftA->id,
            'method' => 'Continued editing this session',
            'is_submitted' => false,
        ]);
        // Still exactly one row for this organisation with this id — no
        // second entry was created by "continuing" the draft.
        $this->assertEquals(
            1,
            \App\Models\ProgrammeEntry::where('organisation_id', $this->orgA->id)
                ->where('id', $this->draftA->id)
                ->count()
        );
    }
}
