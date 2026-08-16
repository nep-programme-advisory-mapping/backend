<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GovernmentAgreementTest extends TestCase
{
    use DatabaseTransactions;

    protected function validPayload(): array
    {
        return [
            'agreements' => [
                [
                    'counterpart_agency' => 'MoEYS national level',
                    'status' => 'active',
                    'institution_name' => 'Test Institution',
                    'nature' => 'MoU',
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

        $response = $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/government-agreements",
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
            "/api/programme-entries/{$entry->id}/government-agreements",
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
            "/api/programme-entries/{$entry->id}/government-agreements",
            $this->validPayload()
        );

        $response->assertStatus(200);
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

        $response = $this->actingAs($coordinator)->putJson(
            "/api/programme-entries/{$entry->id}/government-agreements",
            $this->validPayload()
        );

        $response->assertStatus(200);
    }
}