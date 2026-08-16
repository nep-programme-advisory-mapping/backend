<?php

namespace Tests\Feature;

use App\Models\AdvisoryNote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdviserSubmissionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $memberUser;
    protected User $adminUser;
    protected User $coordinatorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberUser = User::factory()->create([
            'role' => 'member_org',
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'nep_admin',
        ]);

        $this->coordinatorUser = User::factory()->create([
            'role' => 'nep_coordinator',
        ]);
    }

    public function test_nep_admin_can_submit_document()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Ministry of Education',
                'document_name' => 'Education Sector Review 2026',
                'analysis_scope' => 'full map',
            ]);

        $response->assertCreated();
        $response->assertJson([
            'message' => 'Document submitted for analysis.',
        ]);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'submitting_party',
                'document_name',
                'analysis_scope',
                'status',
                'submitted_at',
            ],
        ]);

        $this->assertDatabaseHas('advisory_notes', [
            'submitting_party' => 'Ministry of Education',
            'document_name' => 'Education Sector Review 2026',
            'analysis_scope' => 'full map',
            'status' => 'Submitted for review',
        ]);
    }

    public function test_nep_coordinator_can_submit_document()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'UNICEF Cambodia',
                'document_name' => 'Child Protection Policy Review',
                'analysis_scope' => 'geographic subset',
                'analysis_scope_detail' => 'Focus on rural provinces in the northwest',
            ]);

        $response->assertCreated();
        $response->assertJson([
            'message' => 'Document submitted for analysis.',
        ]);

        $this->assertDatabaseHas('advisory_notes', [
            'submitting_party' => 'UNICEF Cambodia',
            'document_name' => 'Child Protection Policy Review',
            'analysis_scope' => 'geographic subset',
            'analysis_scope_detail' => 'Focus on rural provinces in the northwest',
            'status' => 'Submitted for review',
        ]);
    }

    public function test_member_org_cannot_submit_document()
    {
        $response = $this->actingAs($this->memberUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Local NGO',
                'document_name' => 'Annual Report',
            ]);

        $response->assertForbidden();
        $response->assertJson([
            'message' => 'Forbidden. You do not have the required permissions.',
        ]);

        $this->assertDatabaseMissing('advisory_notes', [
            'submitting_party' => 'Local NGO',
            'document_name' => 'Annual Report',
        ]);
    }

    public function test_unauthenticated_cannot_submit_document()
    {
        $response = $this->postJson('/api/adviser/submissions', [
            'submitting_party' => 'Ministry of Education',
            'document_name' => 'Education Sector Review 2026',
        ]);

        $response->assertUnauthorized();
    }

    public function test_analysis_scope_defaults_to_full_map()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Ministry of Education',
                'document_name' => 'Education Sector Review 2026',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.analysis_scope', 'full map');

        $this->assertDatabaseHas('advisory_notes', [
            'submitting_party' => 'Ministry of Education',
            'document_name' => 'Education Sector Review 2026',
            'analysis_scope' => 'full map',
        ]);
    }

    public function test_analysis_scope_detail_required_for_geographic_subset()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Ministry of Education',
                'document_name' => 'Education Sector Review 2026',
                'analysis_scope' => 'geographic subset',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('analysis_scope_detail');
    }

    public function test_analysis_scope_detail_required_for_thematic_subset()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Ministry of Education',
                'document_name' => 'Education Sector Review 2026',
                'analysis_scope' => 'thematic subset',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('analysis_scope_detail');
    }

    public function test_analysis_scope_detail_cleared_for_full_map()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Ministry of Education',
                'document_name' => 'Education Sector Review 2026',
                'analysis_scope' => 'full map',
                'analysis_scope_detail' => 'This should be cleared',
            ]);

        $response->assertCreated();
        
        $submission = AdvisoryNote::where('document_name', 'Education Sector Review 2026')->first();
        $this->assertNull($submission->analysis_scope_detail);
    }

    public function test_submitting_party_stored_as_free_text()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Ministry of Education',
                'document_name' => 'Education Sector Review 2026',
            ]);

        $response->assertCreated();

        $submission = AdvisoryNote::where('document_name', 'Education Sector Review 2026')->first();
        $this->assertIsString($submission->submitting_party);
        $this->assertNotNull($submission->submitting_party);
        $this->assertGreaterThan(10, strlen($submission->submitting_party)); // Verify it's not truncated
    }

    public function test_required_fields_validation()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['submitting_party', 'document_name']);
    }

    public function test_invalid_analysis_scope_rejected()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Ministry of Education',
                'document_name' => 'Education Sector Review 2026',
                'analysis_scope' => 'invalid scope',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('analysis_scope');
    }

    public function test_can_submit_with_custom_status()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Ministry of Education',
                'document_name' => 'Education Sector Review 2026',
                'analysis_scope' => 'full map',
                'status' => 'Adviser Delivered',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'Adviser Delivered');

        $this->assertDatabaseHas('advisory_notes', [
            'submitting_party' => 'Ministry of Education',
            'document_name' => 'Education Sector Review 2026',
            'status' => 'Adviser Delivered',
        ]);
    }

    public function test_status_defaults_to_submitted_for_review()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Ministry of Education',
                'document_name' => 'Education Sector Review 2026',
                'analysis_scope' => 'full map',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'Submitted for review');

        $this->assertDatabaseHas('advisory_notes', [
            'submitting_party' => 'Ministry of Education',
            'document_name' => 'Education Sector Review 2026',
            'status' => 'Submitted for review',
        ]);
    }
}
