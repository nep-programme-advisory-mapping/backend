<?php

namespace Tests\Feature;

use App\Models\AdvisoryNote;
use App\Models\ProgrammeEntry;
use App\Models\User;
use App\Support\AdvisoryNoteStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /dashboard/recent-activity (BUG-06 regression):
 * the feed's writer and reader must agree on the delivered-status string.
 * A delivered advisory note (status = AdvisoryNoteStatus::ADVICE_DELIVERED,
 * the only value AdviserSubmissionController::markDelivered ever writes)
 * must show up in the feed. Before the fix, the query compared against the
 * bare string 'delivered', which no writer ever produced, so this half of
 * the feed was silently always empty.
 */
class DashboardRecentActivityStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_delivered_advisory_note_appears_in_recent_activity_feed(): void
    {
        $admin = User::factory()->create(['organisation_id' => null, 'role' => 'nep_admin']);
        $entry = ProgrammeEntry::factory()->create(['is_submitted' => true]);

        $note = AdvisoryNote::factory()->create([
            'programme_entry_id' => $entry->id,
            'status' => AdvisoryNoteStatus::ADVICE_DELIVERED,
            'delivered_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/recent-activity');

        $response->assertStatus(200);
        $ids = collect($response->json())
            ->where('type', 'advisory_note')
            ->pluck('id');

        $this->assertTrue($ids->contains($note->id), 'Delivered advisory note is missing from the recent activity feed.');
    }

    public function test_advisory_note_that_was_never_delivered_is_excluded(): void
    {
        $admin = User::factory()->create(['organisation_id' => null, 'role' => 'nep_admin']);
        $entry = ProgrammeEntry::factory()->create(['is_submitted' => true]);

        $note = AdvisoryNote::factory()->create([
            'programme_entry_id' => $entry->id,
            'status' => AdvisoryNoteStatus::SUBMITTED_FOR_REVIEW,
            'delivered_at' => null,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/recent-activity');

        $response->assertStatus(200);
        $ids = collect($response->json())
            ->where('type', 'advisory_note')
            ->pluck('id');

        $this->assertFalse($ids->contains($note->id));
    }
}
