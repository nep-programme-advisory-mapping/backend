<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProgrammeEntryStatusTest extends TestCase
{
    use DatabaseTransactions;

    protected Organisation $orgA;
    protected Organisation $orgB;
    protected User $memberUser;
    protected User $adminUser;
    protected User $coordinatorUser;
    protected ProgrammeEntry $draftEntryOrgA;
    protected ProgrammeEntry $draftEntryOrgB;
    protected ProgrammeEntry $submittedEntryOrgA;
    protected ProgrammeEntry $submittedEntryOrgB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organisation::factory()->create();
        $this->orgB = Organisation::factory()->create();

        $this->memberUser = User::factory()->create([
            'organisation_id' => $this->orgA->id,
            'role' => 'member_org',
        ]);

        $this->adminUser = User::factory()->create([
            'organisation_id' => null,
            'role' => 'nep_admin',
        ]);

        $this->coordinatorUser = User::factory()->create([
            'organisation_id' => null,
            'role' => 'nep_coordinator',
        ]);

        $this->draftEntryOrgA = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->orgA->id,
            'is_submitted' => false,
        ]);

        $this->draftEntryOrgB = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->orgB->id,
            'is_submitted' => false,
        ]);

        $this->submittedEntryOrgA = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->orgA->id,
            'is_submitted' => true,
        ]);

        $this->submittedEntryOrgB = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->orgB->id,
            'is_submitted' => true,
        ]);
    }

    public function test_member_org_can_list_own_draft_entries()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/programme-entries/draft');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->draftEntryOrgA->id);
    }

    public function test_member_org_can_list_own_submitted_entries()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/programme-entries/submitted');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->submittedEntryOrgA->id);
    }

    public function test_member_org_draft_does_not_include_other_org_entries()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/programme-entries/draft');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($this->draftEntryOrgA->id, $ids);
        $this->assertNotContains($this->draftEntryOrgB->id, $ids);
    }

    public function test_member_org_submitted_does_not_include_other_org_entries()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/programme-entries/submitted');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($this->submittedEntryOrgA->id, $ids);
        $this->assertNotContains($this->submittedEntryOrgB->id, $ids);
    }

    public function test_nep_admin_sees_their_own_authored_drafts_not_a_403()
    {
        // draft() delegates staff (no organisation of their own) to
        // myDrafts() — "every organisation's drafts" isn't a meaningful
        // view, so they see entries they personally authored instead. This
        // admin hasn't authored any of the seeded entries, so the list is
        // empty — but the endpoint is reachable (200), not forbidden.
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/programme-entries/draft');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_nep_coordinator_sees_their_own_authored_drafts_not_a_403()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/programme-entries/draft');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_staff_created_draft_appears_in_their_my_drafts_view()
    {
        $ownDraft = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->orgA->id,
            'is_submitted' => false,
            'created_by' => $this->coordinatorUser->id,
        ]);

        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/programme-entries/draft');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownDraft->id);
    }

    public function test_nep_admin_can_list_all_submitted_entries()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/programme-entries/submitted');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($this->submittedEntryOrgA->id, $ids);
        $this->assertContains($this->submittedEntryOrgB->id, $ids);
    }

    public function test_nep_coordinator_can_list_all_submitted_entries()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/programme-entries/submitted');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($this->submittedEntryOrgA->id, $ids);
        $this->assertContains($this->submittedEntryOrgB->id, $ids);
    }

    public function test_draft_endpoint_only_returns_draft_entries()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/programme-entries/draft');

        $response->assertOk();
        foreach ($response->json('data') as $entry) {
            $this->assertFalse($entry['is_submitted']);
        }
    }

    public function test_submitted_endpoint_only_returns_submitted_entries()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/programme-entries/submitted');

        $response->assertOk();
        foreach ($response->json('data') as $entry) {
            $this->assertTrue($entry['is_submitted']);
        }
    }

    public function test_unauthenticated_cannot_access_draft()
    {
        $response = $this->getJson('/api/programme-entries/draft');
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_access_submitted()
    {
        $response = $this->getJson('/api/programme-entries/submitted');
        $response->assertUnauthorized();
    }

    public function test_draft_endpoint_returns_pagination_metadata()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/programme-entries/draft');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'current_page',
            'per_page',
            'total',
            'last_page',
        ]);
        $this->assertEquals(10, $response->json('per_page'));
    }

    public function test_submitted_endpoint_returns_pagination_metadata()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/programme-entries/submitted');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'current_page',
            'per_page',
            'total',
            'last_page',
        ]);
        $this->assertEquals(10, $response->json('per_page'));
    }
}
