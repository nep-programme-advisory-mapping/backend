<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EntryKeywordTest extends TestCase
{
    use DatabaseTransactions;

    protected function validPayload(): array
    {
        return [
            'keywords' => ['education', 'youth', 'rural development'],
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

        $response = $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/keywords",
            $this->validPayload()
        );

        $response->assertStatus(200);
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

        $response = $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/keywords",
            $this->validPayload()
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

        $response = $this->actingAs($admin)->putJson(
            "/api/programme-entries/{$entry->id}/keywords",
            $this->validPayload()
        );

        $response->assertStatus(200);
    }

    public function test_nep_coordinator_can_write_to_any_entry(): void
    {
        // Coordinators hold programmes.create/programmes.update by default
        // (see RolePermissionSeeder) and aren't bound to a single
        // organisation, so — like nep_admin above — they can write to any
        // entry, not just entries at their own organisation (which they
        // don't have one of).
        $organisation = Organisation::factory()->create();
        $coordinator = User::factory()->create(['role' => 'nep_coordinator']);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $response = $this->actingAs($coordinator)->putJson(
            "/api/programme-entries/{$entry->id}/keywords",
            $this->validPayload()
        );

        $response->assertStatus(200);
    }

    public function test_saves_and_replaces_keywords(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/keywords",
            ['keywords' => ['first', 'second']]
        )->assertStatus(200);

        $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/keywords",
            ['keywords' => ['replacement']]
        )->assertStatus(200);

        $entry->refresh();
        $this->assertEquals(['replacement'], $entry->keywords->pluck('keyword')->all());
    }

    public function test_validates_keywords_must_be_array(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $response = $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/keywords",
            ['keywords' => 'not-an-array']
        );

        $response->assertStatus(422);
    }

    public function test_validates_keyword_string_max_length(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);

        $response = $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/keywords",
            ['keywords' => [str_repeat('a', 256)]]
        );

        $response->assertStatus(422);
    }
}
