<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgrammeEntryAuditTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $memberUser;
    protected User $adminUser;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberUser = User::factory()->create([
            'role' => 'member_org',
            'organisation_id' => Organisation::factory(),
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'nep_admin',
            'organisation_id' => $this->memberUser->organisation_id,
        ]);

        $this->otherUser = User::factory()->create([
            'role' => 'member_org',
            'organisation_id' => Organisation::factory(),
        ]);
    }

    public function test_last_updated_by_is_set_on_create(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->postJson('/api/programme-entries', [
                'programme_name' => 'Test Programme',
                'start_year' => 2026,
            ]);

        $response->assertCreated();
        
        $entry = ProgrammeEntry::first();
        $this->assertEquals($this->memberUser->id, $entry->last_updated_by);
    }

    public function test_last_updated_by_is_set_on_update(): void
    {
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->memberUser->organisation_id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/programme-entries/{$entry->id}", [
                'programme_name' => 'Updated Programme Name',
            ]);

        $response->assertOk();
        
        $entry->refresh();
        $this->assertEquals($this->adminUser->id, $entry->last_updated_by);
    }

    public function test_client_cannot_override_last_updated_by_on_create(): void
    {
        $maliciousUserId = $this->otherUser->id;
        
        $response = $this->actingAs($this->memberUser)
            ->postJson('/api/programme-entries', [
                'programme_name' => 'Test Programme',
                'start_year' => 2026,
                'last_updated_by' => $maliciousUserId,
            ]);

        $response->assertCreated();
        
        $entry = ProgrammeEntry::first();
        // Should be set to authenticated user, not the malicious value
        $this->assertEquals($this->memberUser->id, $entry->last_updated_by);
        $this->assertNotEquals($maliciousUserId, $entry->last_updated_by);
    }

    public function test_client_cannot_override_last_updated_by_on_update(): void
    {
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->memberUser->organisation_id,
            'last_updated_by' => $this->memberUser->id,
        ]);

        $maliciousUserId = $this->otherUser->id;
        
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/programme-entries/{$entry->id}", [
                'programme_name' => 'Updated Programme Name',
                'last_updated_by' => $maliciousUserId,
            ]);

        $response->assertOk();
        
        $entry->refresh();
        // Should be set to authenticated user (admin), not the malicious value
        $this->assertEquals($this->adminUser->id, $entry->last_updated_by);
        $this->assertNotEquals($maliciousUserId, $entry->last_updated_by);
    }

    public function test_last_updated_by_changes_with_each_update(): void
    {
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->memberUser->organisation_id,
        ]);

        $entry->refresh();
        $firstUpdater = $entry->last_updated_by;

        // Update by admin
        $this->actingAs($this->adminUser)
            ->putJson("/api/programme-entries/{$entry->id}", [
                'programme_name' => 'First Update',
            ]);

        $entry->refresh();
        $this->assertEquals($this->adminUser->id, $entry->last_updated_by);
        $this->assertNotEquals($firstUpdater, $entry->last_updated_by);

        // Update by member user
        $this->actingAs($this->memberUser)
            ->putJson("/api/programme-entries/{$entry->id}", [
                'programme_name' => 'Second Update',
            ]);

        $entry->refresh();
        $this->assertEquals($this->memberUser->id, $entry->last_updated_by);
        $this->assertNotEquals($this->adminUser->id, $entry->last_updated_by);
    }

    public function test_last_updated_by_is_set_when_verifying_entry(): void
    {
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->memberUser->organisation_id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/programme-entries/{$entry->id}/verify");

        $response->assertOk();
        
        $entry->refresh();
        $this->assertEquals($this->adminUser->id, $entry->last_updated_by);
    }

    public function test_last_updated_by_is_set_when_saving_model_directly(): void
    {
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->memberUser->organisation_id,
        ]);

        // Simulate authenticated user saving the model
        $this->actingAs($this->adminUser);
        $entry->programme_name = 'Direct Save Update';
        $entry->save();

        $entry->refresh();
        $this->assertEquals($this->adminUser->id, $entry->last_updated_by);
    }

    public function test_last_updated_by_is_null_when_no_authenticated_user(): void
    {
        // Create entry without authentication (e.g., in a seeder or factory)
        $entry = new ProgrammeEntry([
            'organisation_id' => $this->memberUser->organisation_id,
            'programme_name' => 'Test',
            'start_year' => 2026,
        ]);
        $entry->save();

        $entry->refresh();
        $this->assertNull($entry->last_updated_by);
    }

    public function test_mass_assignment_attempt_is_ignored(): void
    {
        $entry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->memberUser->organisation_id,
        ]);

        $maliciousUserId = $this->otherUser->id;
        
        // Attempt mass assignment with malicious last_updated_by
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/programme-entries/{$entry->id}", [
                'programme_name' => 'Mass Assignment Test',
                'last_updated_by' => $maliciousUserId,
            ]);

        $response->assertOk();
        
        $entry->refresh();
        // The field should be set by the backend, not from the request
        $this->assertEquals($this->adminUser->id, $entry->last_updated_by);
        $this->assertNotEquals($maliciousUserId, $entry->last_updated_by);
    }
}