<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrganisationProfileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_member_org_can_view_own_organisation(): void
    {
        $organisation = Organisation::factory()->create([
            'name' => 'Green Earth Nepal',
            'contact_name' => 'Ram Sharma',
            'email' => 'info@greenearth.org',
            'member_since' => 2020,
        ]);
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);

        $response = $this->actingAs($user)->getJson('/api/organisations/me');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $organisation->id,
                    'name' => 'Green Earth Nepal',
                    'contact_name' => 'Ram Sharma',
                    'email' => 'info@greenearth.org',
                    'member_since' => 2020,
                ],
            ]);
    }

    public function test_member_org_can_update_contact_name(): void
    {
        $organisation = Organisation::factory()->create([
            'contact_name' => 'Old Name',
        ]);
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);

        $response = $this->actingAs($user)->patchJson('/api/organisations/me', [
            'contact_name' => 'New Contact Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.contact_name', 'New Contact Name');

        $this->assertDatabaseHas('organisations', [
            'id' => $organisation->id,
            'contact_name' => 'New Contact Name',
        ]);
    }

    public function test_user_without_organisation_gets_404(): void
    {
        $user = User::factory()->create([
            'organisation_id' => null,
            'role' => 'nep_admin',
        ]);

        $this->actingAs($user)->getJson('/api/organisations/me')
            ->assertStatus(404);

        $this->actingAs($user)->patchJson('/api/organisations/me', [
            'contact_name' => 'Should Fail',
        ])->assertStatus(404);
    }

    public function test_unauthenticated_user_is_blocked(): void
    {
        $this->getJson('/api/organisations/me')->assertStatus(401);
        $this->patchJson('/api/organisations/me', [
            'contact_name' => 'Should Fail',
        ])->assertStatus(401);
    }

    public function test_validates_contact_name_must_be_string(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);

        $this->actingAs($user)->patchJson('/api/organisations/me', [
            'contact_name' => 123,
        ])->assertStatus(422);
    }

    public function test_validates_email_format(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);

        $this->actingAs($user)->patchJson('/api/organisations/me', [
            'email' => 'not-an-email',
        ])->assertStatus(422);
    }
}
