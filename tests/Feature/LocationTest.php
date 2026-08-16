<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\ProgrammeLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_list_all_provinces(): void
    {
        $user = User::factory()->create();
        Province::create(['province_name' => 'Province A']);
        Province::create(['province_name' => 'Province B']);

        $response = $this->actingAs($user)->getJson('/api/provinces');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'province_name'],
                ],
            ]);
    }

    public function test_can_list_districts_for_valid_province(): void
    {
        $user = User::factory()->create();
        $province = Province::create(['province_name' => 'Test Province']);
        District::create(['province_id' => $province->id, 'name' => 'District 1']);
        District::create(['province_id' => $province->id, 'name' => 'District 2']);

        $response = $this->actingAs($user)->getJson("/api/provinces/{$province->id}/districts");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'District 1')
            ->assertJsonPath('data.1.name', 'District 2');
    }

    public function test_returns_empty_districts_for_province_with_no_districts(): void
    {
        $user = User::factory()->create();
        $province = Province::create(['province_name' => 'Empty Province']);

        $response = $this->actingAs($user)->getJson("/api/provinces/{$province->id}/districts");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_returns_404_for_nonexistent_province(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/provinces/99999/districts');

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access_provinces(): void
    {
        $response = $this->getJson('/api/provinces');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_districts(): void
    {
        $response = $this->getJson('/api/provinces/1/districts');

        $response->assertUnauthorized();
    }

    // ==================== PROVINCE PROGRAMME COUNTS TESTS ====================

    public function test_province_counts_returns_zero_for_unused_provinces(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);
        Province::create(['province_name' => 'Unused Province']);

        $response = $this->actingAs($admin)->getJson('/api/provinces/counts');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'province_name' => 'Unused Province',
            'programme_count' => 0,
        ]);
    }

    public function test_province_counts_counts_programmes_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'nep_admin']);

        $provinceA = Province::create(['province_name' => 'Province A']);
        $provinceB = Province::create(['province_name' => 'Province B']);

        $org = Organisation::factory()->create();

        // Entry 1: in province A
        $entry1 = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $provinceA->id,
        ]);

        // Entry 2: in province B
        $entry2 = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry2->id,
            'province_id' => $provinceB->id,
        ]);

        // Entry 3: in both provinces
        $entry3 = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry3->id,
            'province_id' => $provinceA->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry3->id,
            'province_id' => $provinceB->id,
        ]);

        // Entry 4: no geography (should not appear)
        ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);

        $response = $this->actingAs($admin)->getJson('/api/provinces/counts');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'province_name' => 'Province A',
            'programme_count' => 2, // entry1 + entry3
        ]);

        $response->assertJsonFragment([
            'province_name' => 'Province B',
            'programme_count' => 2, // entry2 + entry3
        ]);
    }

    public function test_non_admin_cannot_access_province_counts(): void
    {
        $member = User::factory()->create(['role' => 'member_org']);

        $response = $this->actingAs($member)->getJson('/api/provinces/counts');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_province_counts(): void
    {
        $response = $this->getJson('/api/provinces/counts');

        $response->assertStatus(401);
    }
}
