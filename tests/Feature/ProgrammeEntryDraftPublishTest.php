<?php

namespace Tests\Feature;

use App\Models\ActivityItem;
use App\Models\BudgetBand;
use App\Models\EntryKeyword;
use App\Models\Organisation;
use App\Models\ProgrammeActivity;
use App\Models\ProgrammeEntry;
use App\Models\ProgrammeLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Covers the draft/autosave vs explicit-publish separation: autosave and
 * plain draft saves must never trip required-field validation or touch
 * is_submitted, while an explicit publish attempt enforces the full Section 1
 * field set plus cross-resource completeness (activities/geography/keywords).
 */
class ProgrammeEntryDraftPublishTest extends TestCase
{
    use DatabaseTransactions;

    protected Organisation $organisation;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->member = User::factory()->create([
            'organisation_id' => $this->organisation->id,
            'role' => 'member_org',
        ]);
    }

    public function test_a_completely_empty_entry_can_be_created_as_a_draft(): void
    {
        $response = $this->actingAs($this->member)->postJson('/api/programme-entries', []);

        $response->assertStatus(201);
        $this->assertDatabaseHas('programme_entries', [
            'id' => $response->json('data.id'),
            'organisation_id' => $this->organisation->id,
            'is_submitted' => false,
        ]);
    }

    public function test_a_draft_can_be_autosave_updated_with_partial_data_and_no_submit_flag(): void
    {
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->organisation->id,
            'is_submitted' => false,
        ]);

        // Simulates an autosave tick: only a couple of fields changed, no
        // is_submitted key sent at all — must not require the rest of
        // Section 1 and must not error.
        $response = $this->actingAs($this->member)->putJson(
            "/api/programme-entries/{$entry->id}",
            ['method' => 'Updated via autosave']
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('programme_entries', [
            'id' => $entry->id,
            'method' => 'Updated via autosave',
            'is_submitted' => false,
        ]);
    }

    public function test_autosave_style_update_never_unpublishes_an_already_submitted_entry(): void
    {
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->organisation->id,
            'is_submitted' => true,
        ]);

        // No is_submitted in the payload at all — this is what the frontend
        // now sends for autosave/plain "Save & exit" (programmeSave.ts).
        $response = $this->actingAs($this->member)->putJson(
            "/api/programme-entries/{$entry->id}",
            ['method' => 'A small edit to a published entry']
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('programme_entries', [
            'id' => $entry->id,
            'is_submitted' => true,
        ]);
    }

    public function test_publishing_requires_full_section1_fields(): void
    {
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->organisation->id,
            'is_submitted' => false,
            'budget_band_id' => null,
        ]);

        $response = $this->actingAs($this->member)->putJson(
            "/api/programme-entries/{$entry->id}",
            ['is_submitted' => true]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['budget_band_id']);
        $this->assertDatabaseHas('programme_entries', [
            'id' => $entry->id,
            'is_submitted' => false,
        ]);
    }

    public function test_publishing_requires_at_least_one_activity_geography_entry_and_keyword(): void
    {
        $budgetBand = BudgetBand::factory()->create(['label' => 'Under $50,000', 'min_amount' => 0, 'max_amount' => 49999]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->organisation->id,
            'is_submitted' => false,
            'budget_band_id' => $budgetBand->id,
            'ongoing' => true,
            'fte_staff' => 1,
            'direct_beneficiaries' => 10,
            'indirect_beneficiaries' => 20,
        ]);
        // Deliberately no activities, locations, or keywords attached.

        $response = $this->actingAs($this->member)->putJson(
            "/api/programme-entries/{$entry->id}",
            ['is_submitted' => true]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['activities', 'provinces', 'keywords']);
        $this->assertDatabaseHas('programme_entries', [
            'id' => $entry->id,
            'is_submitted' => false,
        ]);
    }

    public function test_publishing_succeeds_once_everything_is_complete(): void
    {
        $budgetBand = BudgetBand::factory()->create(['label' => 'Under $50,000', 'min_amount' => 0, 'max_amount' => 49999]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->organisation->id,
            'is_submitted' => false,
            'budget_band_id' => $budgetBand->id,
            'ongoing' => true,
            'fte_staff' => 1,
            'direct_beneficiaries' => 10,
            'indirect_beneficiaries' => 20,
        ]);
        $item = ActivityItem::factory()->create(['is_active' => true]);
        ProgrammeActivity::factory()->create([
            'programme_entry_id' => $entry->id,
            'activity_item_id' => $item->id,
        ]);
        $province = Province::factory()->create(['province_name' => 'Kampong Cham']);
        ProgrammeLocation::factory()->create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
        ]);
        EntryKeyword::factory()->create([
            'programme_entry_id' => $entry->id,
            'keyword' => 'literacy',
        ]);

        $response = $this->actingAs($this->member)->putJson(
            "/api/programme-entries/{$entry->id}",
            ['is_submitted' => true]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('programme_entries', [
            'id' => $entry->id,
            'is_submitted' => true,
        ]);
    }
}
