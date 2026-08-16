<?php

namespace Tests\Feature;

use App\Models\AdvisoryNote;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /adviser/programme-entries/{programmeEntry}/advisory-note (BUG-04):
 * a member org must only ever see the advisory note for its own programme entry.
 */
class AdviserAdvisoryNoteVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function endpoint(ProgrammeEntry $entry): string
    {
        return "/api/adviser/programme-entries/{$entry->id}/advisory-note";
    }

    public function test_member_org_can_view_own_organisations_delivered_advisory_note(): void
    {
        $org = Organisation::factory()->create();
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);
        AdvisoryNote::factory()->create([
            'programme_entry_id' => $entry->id,
            'status' => 'advice_delivered',
        ]);

        $member = User::factory()->create(['role' => 'member_org', 'organisation_id' => $org->id]);

        $response = $this->actingAs($member, 'sanctum')->getJson($this->endpoint($entry));

        $response->assertStatus(200);
    }

    public function test_member_org_cannot_view_another_organisations_advisory_note(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $otherEntry = ProgrammeEntry::factory()->create(['organisation_id' => $otherOrg->id]);
        AdvisoryNote::factory()->create([
            'programme_entry_id' => $otherEntry->id,
            'status' => 'advice_delivered',
        ]);

        $member = User::factory()->create(['role' => 'member_org', 'organisation_id' => $ownOrg->id]);

        $response = $this->actingAs($member, 'sanctum')->getJson($this->endpoint($otherEntry));

        $response->assertStatus(404);
    }

    public function test_member_org_cannot_view_own_entrys_note_before_it_is_delivered(): void
    {
        $org = Organisation::factory()->create();
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);
        AdvisoryNote::factory()->create([
            'programme_entry_id' => $entry->id,
            'status' => 'analysed', // not yet delivered
        ]);

        $member = User::factory()->create(['role' => 'member_org', 'organisation_id' => $org->id]);

        $response = $this->actingAs($member, 'sanctum')->getJson($this->endpoint($entry));

        $response->assertStatus(404);
    }

    public function test_coordinator_can_view_any_organisations_advisory_note(): void
    {
        $org = Organisation::factory()->create();
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);
        AdvisoryNote::factory()->create([
            'programme_entry_id' => $entry->id,
            'status' => 'analysed',
        ]);

        $coordinator = User::factory()->create(['role' => 'nep_coordinator']);

        $response = $this->actingAs($coordinator, 'sanctum')->getJson($this->endpoint($entry));

        $response->assertStatus(200);
    }
}
