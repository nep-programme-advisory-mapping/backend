<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProgrammeGeographyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_store_accepts_selected_province_with_zero_districts(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $province = Province::create(['province_name' => 'Test Province']);

        $response = $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/geography",
            [
                'provinces' => [
                    [
                        'province_id' => $province->id,
                        'district_ids' => [],
                    ],
                ],
                'other_countries' => [],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Geography saved.')
            ->assertJsonPath('data.0.province_id', $province->id)
            ->assertJsonPath('data.0.district_id', null);

        $this->assertDatabaseHas('programme_geography', [
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => null,
            'country' => null,
        ]);
    }

    public function test_store_rejects_district_that_is_not_child_of_selected_province(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
        ]);
        $selectedProvince = Province::create(['province_name' => 'Selected Province']);
        $otherProvince = Province::create(['province_name' => 'Other Province']);
        $otherDistrict = District::create([
            'province_id' => $otherProvince->id,
            'name' => 'Other District',
        ]);

        $response = $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/geography",
            [
                'provinces' => [
                    [
                        'province_id' => $selectedProvince->id,
                        'district_ids' => [$otherDistrict->id],
                    ],
                ],
                'other_countries' => [],
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('provinces.0.district_ids');
    }

    public function test_member_org_cannot_write_to_another_organisations_entry(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $ownOrg->id, 'role' => 'member_org']);
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $otherOrg->id]);

        $response = $this->actingAs($user)->putJson(
            "/api/programme-entries/{$entry->id}/geography",
            ['provinces' => [], 'other_countries' => []]
        );

        $response->assertStatus(404);
    }

    public function test_nep_coordinator_can_read_and_write(): void
    {
        // Coordinators hold programmes.create/programmes.update by default
        // and aren't bound to a single organisation (see RolePermissionSeeder),
        // so they can both read and write any entry's geography.
        $organisation = Organisation::factory()->create();
        $coordinator = User::factory()->create(['role' => 'nep_coordinator']);
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $organisation->id]);

        $readResponse = $this->actingAs($coordinator)->getJson(
            "/api/programme-entries/{$entry->id}/geography"
        );
        $readResponse->assertStatus(200);

        $writeResponse = $this->actingAs($coordinator)->putJson(
            "/api/programme-entries/{$entry->id}/geography",
            ['provinces' => [], 'other_countries' => []]
        );
        $writeResponse->assertStatus(200);
    }

    public function test_nep_admin_can_write_to_any_entry(): void
    {
        $organisation = Organisation::factory()->create();
        $admin = User::factory()->create(['role' => 'nep_admin']);
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $organisation->id]);

        $response = $this->actingAs($admin)->putJson(
            "/api/programme-entries/{$entry->id}/geography",
            ['provinces' => [], 'other_countries' => []]
        );

        $response->assertStatus(200);
    }
}
