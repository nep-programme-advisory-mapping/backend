<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProgrammeEntryTest extends TestCase
{
    use DatabaseTransactions;

    protected Organisation $orgA;
    protected Organisation $orgB;
    protected User $memberUser;
    protected User $adminUser;
    protected User $coordinatorUser;
    protected ProgrammeEntry $entryInOrgA;
    protected ProgrammeEntry $entryInOrgB;

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
            'organisation_id' => $this->orgA->id,
            'role' => 'nep_admin',
        ]);

        $this->coordinatorUser = User::factory()->create([
            'organisation_id' => null,
            'role' => 'nep_coordinator',
        ]);

        $this->entryInOrgA = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->orgA->id,
        ]);

        $this->entryInOrgB = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->orgB->id,
        ]);
    }

    /**
     * @dataProvider roleCanListOwnOrgProvider
     */
    public function test_index_returns_own_org_entries(string $role)
    {
        $user = User::factory()->create([
            'organisation_id' => $this->orgA->id,
            'role' => $role,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organisations/{$this->orgA->id}/programme-entries");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->entryInOrgA->id);
    }

    /**
     * @dataProvider roleCanListAnyOrgProvider
     */
    public function test_index_nep_staff_can_list_any_org(string $role)
    {
        $user = User::factory()->create([
            'organisation_id' => null,
            'role' => $role,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/organisations/{$this->orgB->id}/programme-entries");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->entryInOrgB->id);
    }

    public function test_member_org_gets_404_for_other_org_index()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson("/api/organisations/{$this->orgB->id}/programme-entries");

        $response->assertNotFound();
        $response->assertJsonPath('message', 'Not Found.');
    }

    public function test_member_org_can_view_own_entry()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson("/api/programme-entries/{$this->entryInOrgA->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $this->entryInOrgA->id);
    }

    public function test_member_org_gets_404_for_other_org_entry()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson("/api/programme-entries/{$this->entryInOrgB->id}");

        $response->assertNotFound();
        $response->assertJsonPath('message', 'Not Found.');
    }

    public function test_nep_admin_can_view_any_entry()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/programme-entries/{$this->entryInOrgB->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $this->entryInOrgB->id);
    }

    public function test_coordinator_can_view_entry_in_any_org(): void
    {
        // Coordinators aren't bound to a single organisation (organisation_id
        // is null — see setUp()), so — like nep_admin — they can view any
        // entry, matching test_index_nep_staff_can_list_any_org's org-listing
        // equivalent above.
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson("/api/programme-entries/{$this->entryInOrgA->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $this->entryInOrgA->id);
    }

    public function test_show_404_for_nonexistent_entry()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/programme-entries/999999');

        $response->assertNotFound();
    }

    public function test_index_404_for_nonexistent_org()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/organisations/999999/programme-entries');

        $response->assertNotFound();
    }

    public function test_unauthenticated_cannot_access_index()
    {
        $response = $this->getJson("/api/organisations/{$this->orgA->id}/programme-entries");
        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_access_show()
    {
        $response = $this->getJson("/api/programme-entries/{$this->entryInOrgA->id}");
        $response->assertUnauthorized();
    }

    public static function roleCanListOwnOrgProvider(): array
    {
        return [['member_org'], ['nep_admin'], ['nep_coordinator']];
    }

    public static function roleCanListAnyOrgProvider(): array
    {
        return [['nep_admin'], ['nep_coordinator']];
    }
}
