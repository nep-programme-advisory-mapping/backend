<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Permission;
use App\Models\ProgrammeEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 goal for the Programme Entries module: a custom (non-built-in)
 * role granted the right permissions should be able to use the feature,
 * scoped correctly by whether it's bound to an organisation — without any
 * of the previous hardcoded role-name checks. Also locks in that the core
 * CRUD routes, which previously carried no permission middleware at all,
 * now actually require one.
 */
class ProgrammeEntryCustomRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function grantedUser(string $roleName, array $permissionNames, ?int $organisationId = null): User
    {
        $role = Role::create(['name' => $roleName, 'display_name' => $roleName]);
        $role->permissions()->sync(
            collect($permissionNames)->map(
                fn ($name) => Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'group' => explode('.', $name)[0]])->id
            )
        );

        $user = User::create([
            'name' => ucfirst($roleName), 'email' => "{$roleName}@test.com", 'password' => bcrypt('password'),
            'role' => $roleName, 'status' => 'active', 'organisation_id' => $organisationId,
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_a_custom_org_bound_role_with_programmes_create_can_create_its_own_entry(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->grantedUser('field_officer', ['programmes.create'], $org->id);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/programme-entries', [
            'programme_name' => 'Custom Role Programme',
            'start_year' => 2026,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('programme_entries', [
            'programme_name' => 'Custom Role Programme',
            'organisation_id' => $org->id,
        ]);
    }

    public function test_a_custom_staff_like_role_with_programmes_create_can_create_on_behalf_of_any_org(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->grantedUser('programme_liaison', ['programmes.create'], null);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/programme-entries', [
            'programme_name' => 'Liaison-created Programme',
            'start_year' => 2026,
            'organisation_id' => $org->id,
        ]);

        $response->assertStatus(201);
        $entry = ProgrammeEntry::where('programme_name', 'Liaison-created Programme')->first();
        $this->assertNotNull($entry);
        $this->assertFalse((bool) $entry->is_submitted); // staff-created entries are always draft
    }

    public function test_a_custom_org_bound_role_with_programmes_update_can_only_touch_its_own_org(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $user = $this->grantedUser('field_officer', ['programmes.view', 'programmes.update'], $ownOrg->id);

        $ownEntry = ProgrammeEntry::factory()->create(['organisation_id' => $ownOrg->id]);
        $otherEntry = ProgrammeEntry::factory()->create(['organisation_id' => $otherOrg->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/programme-entries/{$ownEntry->id}", ['programme_name' => 'Updated'])
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/programme-entries/{$otherEntry->id}", ['programme_name' => 'Updated'])
            ->assertStatus(403); // canManage() fails -> update()'s own 403 message
    }

    public function test_a_custom_staff_like_role_with_programmes_view_can_view_any_org_entry(): void
    {
        $org = Organisation::factory()->create();
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);
        $user = $this->grantedUser('reviewer', ['programmes.view'], null);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/programme-entries/{$entry->id}")
            ->assertStatus(200);
    }

    public function test_a_role_with_no_programmes_permissions_at_all_is_blocked_at_the_route(): void
    {
        // Regression test: /programme-entries, /programme-entries/{id}, and
        // sibling routes used to carry only auth:sanctum — no permission
        // middleware — so any authenticated user, regardless of grants,
        // could reach them.
        $org = Organisation::factory()->create();
        $entry = ProgrammeEntry::factory()->create(['organisation_id' => $org->id]);
        $bareRole = Role::create(['name' => 'bare', 'display_name' => 'Bare']);
        $user = User::create([
            'name' => 'Bare', 'email' => 'bare@test.com', 'password' => bcrypt('password'),
            'role' => 'bare', 'status' => 'active',
        ]);
        $user->roles()->attach($bareRole);

        $this->actingAs($user, 'sanctum')->getJson('/api/programme-entries')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson("/api/programme-entries/{$entry->id}")->assertStatus(403);
        $this->actingAs($user, 'sanctum')->postJson('/api/programme-entries', [
            'programme_name' => 'Should not be created', 'start_year' => 2026,
        ])->assertStatus(403);
        $this->assertDatabaseMissing('programme_entries', ['programme_name' => 'Should not be created']);
    }
}
