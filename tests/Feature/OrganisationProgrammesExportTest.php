<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /organisations/{organisation}/programme-entries/pdf now chunks its
 * query the same way MapExportController::exportPdf does — a single ->get()
 * with the same deep eager-loads was the one export path left unaligned.
 */
class OrganisationProgrammesExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_export_includes_all_submitted_entries_across_chunk_boundaries(): void
    {
        $admin = User::factory()->create(['organisation_id' => null, 'role' => 'nep_admin']);
        $organisation = Organisation::factory()->create();

        // More than one chunk (chunk size is 50) so a chunking regression
        // (e.g. only the first page ever ending up in the report) would fail this.
        ProgrammeEntry::factory()->count(60)->create([
            'organisation_id' => $organisation->id,
            'is_submitted' => true,
        ]);
        // A draft (not submitted) entry must be excluded either way.
        ProgrammeEntry::factory()->create([
            'organisation_id' => $organisation->id,
            'is_submitted' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/organisations/{$organisation->id}/programme-entries/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_member_org_cannot_export_another_organisations_programmes(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $member = User::factory()->create(['role' => 'member_org', 'organisation_id' => $ownOrg->id]);

        $response = $this->actingAs($member, 'sanctum')
            ->get("/api/organisations/{$otherOrg->id}/programme-entries/pdf");

        $response->assertStatus(403);
    }
}
