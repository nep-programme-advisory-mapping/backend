<?php

namespace Tests\Feature;

use App\Models\ActivityItem;
use App\Models\EducationLevel;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProgrammeActivityTest extends TestCase
{
    use DatabaseTransactions;

    protected function validPayload(ActivityItem $item, EducationLevel $level): array
    {
        return [
            'activities' => [
                [
                    'activity_item_id' => $item->id,
                    'is_primary' => true,
                    'education_level_ids' => [$level->id],
                ],
            ],
        ];
    }

    public function test_member_org_can_write_to_own_entry(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $item = ActivityItem::factory()->create(['is_active' => true]);
        $level = EducationLevel::factory()->create();

        $response = $this->actingAs($user)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $this->validPayload($item, $level)
        );

        $response->assertStatus(201);
    }

    public function test_member_org_cannot_write_to_another_organisations_entry(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $ownOrg->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $otherOrg->id,
        ]);
        $item = ActivityItem::factory()->create(['is_active' => true]);
        $level = EducationLevel::factory()->create();

        $response = $this->actingAs($user)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $this->validPayload($item, $level)
        );

        $response->assertStatus(404);
    }

    public function test_nep_admin_can_write_to_any_entry(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $item = ActivityItem::factory()->create(['is_active' => true]);
        $level = EducationLevel::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $this->validPayload($item, $level)
        );

        $response->assertStatus(201);
    }

    public function test_nep_coordinator_can_write_to_any_entry(): void
    {
        // See EntryKeywordTest::test_nep_coordinator_can_write_to_any_entry
        // for why: programmes.create/update by default, not org-bound.
        $organisation = Organisation::factory()->create();
        $coordinator = User::factory()->create(['role' => 'nep_coordinator']);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $item = ActivityItem::factory()->create(['is_active' => true]);
        $level = EducationLevel::factory()->create();

        $response = $this->actingAs($coordinator)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $this->validPayload($item, $level)
        );

        $response->assertStatus(201);
    }

    public function test_other_activity_requires_free_text(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $item = ActivityItem::factory()->create([
            'is_active' => true,
            'is_other' => true,
        ]);
        $level = EducationLevel::factory()->create();
        $payload = $this->validPayload($item, $level);
        $payload['activities'][0]['other_text'] = '   ';

        $response = $this->actingAs($user)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $payload
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['activities.0.other_text']);
    }

    public function test_other_activity_free_text_is_stored_in_review_queue(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $item = ActivityItem::factory()->create([
            'is_active' => true,
            'is_other' => true,
        ]);
        $level = EducationLevel::factory()->create();
        $payload = $this->validPayload($item, $level);
        $payload['activities'][0]['other_text'] = 'Community radio literacy programme';

        $response = $this->actingAs($user)->postJson(
            "/api/programme-entries/{$entry->id}/activities",
            $payload
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('taxonomy_other_queues', [
            'programme_entry_id' => $entry->id,
            'item_id' => $item->id,
            'other_text' => 'Community radio literacy programme',
            'suggested_subcategory_id' => $item->subcategory_id,
            'status' => 'pending',
        ]);
    }
}
