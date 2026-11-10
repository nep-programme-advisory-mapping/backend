<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EntryCollaborationTest extends TestCase
{
    use DatabaseTransactions;

    private function memberWithEntry(): array
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        return [$user, $entry];
    }

    private function endpoint(ProgrammeEntry $entry): string
    {
        return "/api/programme-entries/{$entry->id}/collaborations";
    }

    public function test_member_org_can_save_collaborations_on_own_entry(): void
    {
        [$user, $entry] = $this->memberWithEntry();

        $this->actingAs($user)
            ->putJson($this->endpoint($entry), ['collaborations' => ['Example NGO', 'Example Foundation']])
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.organisation_name', 'Example NGO');

        $this->assertSame(
            ['Example NGO', 'Example Foundation'],
            $entry->collaborations()->pluck('organisation_name')->all()
        );
    }

    public function test_member_org_cannot_write_to_another_organisations_entry(): void
    {
        [$user] = $this->memberWithEntry();
        $otherEntry = ProgrammeEntry::factory()->create([
            'organisation_id' => Organisation::factory()->create()->id,
        ]);

        $this->actingAs($user)
            ->putJson($this->endpoint($otherEntry), ['collaborations' => ['Example NGO']])
            ->assertStatus(404);

        $this->assertSame(0, $otherEntry->collaborations()->count());
    }

    public function test_staff_can_write_to_any_entry(): void
    {
        [, $entry] = $this->memberWithEntry();

        foreach (['nep_admin', 'nep_coordinator'] as $role) {
            $staff = User::factory()->create(['role' => $role]);

            $this->actingAs($staff)
                ->putJson($this->endpoint($entry), ['collaborations' => ["Saved by {$role}"]])
                ->assertStatus(200);
        }
    }

    public function test_saving_replaces_existing_and_an_empty_list_clears_them(): void
    {
        [$user, $entry] = $this->memberWithEntry();

        $this->actingAs($user)->putJson($this->endpoint($entry), ['collaborations' => ['First', 'Second']])->assertStatus(200);
        $this->actingAs($user)->putJson($this->endpoint($entry), ['collaborations' => ['Replacement']])->assertStatus(200);
        $this->assertSame(['Replacement'], $entry->collaborations()->pluck('organisation_name')->all());

        $this->actingAs($user)->putJson($this->endpoint($entry), ['collaborations' => []])->assertStatus(200);
        $this->assertSame(0, $entry->collaborations()->count());
    }

    public function test_allows_five_but_rejects_six_organisations(): void
    {
        [$user, $entry] = $this->memberWithEntry();

        $this->actingAs($user)
            ->putJson($this->endpoint($entry), ['collaborations' => ['A', 'B', 'C', 'D', 'E']])
            ->assertStatus(200);

        $this->actingAs($user)
            ->putJson($this->endpoint($entry), ['collaborations' => ['A', 'B', 'C', 'D', 'E', 'F']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['collaborations']);

        $this->assertSame(5, $entry->collaborations()->count());
    }

    public function test_validates_array_blank_values_and_max_length(): void
    {
        [$user, $entry] = $this->memberWithEntry();

        foreach (['not-an-array', [''], [str_repeat('a', 256)]] as $invalid) {
            $this->actingAs($user)
                ->putJson($this->endpoint($entry), ['collaborations' => $invalid])
                ->assertStatus(422);
        }
    }

    public function test_entry_response_includes_collaborations(): void
    {
        [$user, $entry] = $this->memberWithEntry();
        $entry->collaborations()->create(['organisation_name' => 'Example NGO']);

        $this->actingAs($user)
            ->getJson("/api/programme-entries/{$entry->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.collaborations.0.organisation_name', 'Example NGO');
    }

    public function test_collaborations_are_optional_for_publishing(): void
    {
        // Section 6 must not become a new publish requirement: the cross-resource
        // check only names activities, provinces and keywords.
        [$user, $entry] = $this->memberWithEntry();

        $this->actingAs($user)
            ->putJson("/api/programme-entries/{$entry->id}", ['is_submitted' => true])
            ->assertJsonMissingValidationErrors(['collaborations']);
    }
}
