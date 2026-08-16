<?php

namespace Tests\Feature;

use App\Models\AdvisoryNote;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdviserSubmissionListTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $coordinatorUser;
    protected User $memberUser;
    protected Organisation $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organisation::factory()->create();

        $this->adminUser = User::factory()->create([
            'role' => 'nep_admin',
        ]);

        $this->coordinatorUser = User::factory()->create([
            'role' => 'nep_coordinator',
        ]);

        $this->memberUser = User::factory()->create([
            'organisation_id' => $this->org->id,
            'role' => 'member_org',
        ]);
    }

    // ----------------------------------------------------------------
    //  Permission checks
    // ----------------------------------------------------------------

    public function test_nep_admin_can_retrieve_submissions()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/adviser/submissions');

        $response->assertOk();
    }

    public function test_nep_coordinator_can_retrieve_submissions()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/adviser/submissions');

        $response->assertOk();
    }

    public function test_member_org_cannot_retrieve_submissions()
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/adviser/submissions');

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_retrieve_submissions()
    {
        $response = $this->getJson('/api/adviser/submissions');

        $response->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    //  Response structure
    // ----------------------------------------------------------------

    public function test_response_includes_required_fields()
    {
        AdvisoryNote::factory()->create([
            'submitting_party' => 'Ministry of Education',
            'analysis_scope' => 'full map',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/adviser/submissions');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'submitting_party',
                    'analysis_scope',
                    'status',
                    'submitted_at',
                ],
            ],
        ]);
    }

    // ----------------------------------------------------------------
    //  Filtering
    // ----------------------------------------------------------------

    public function test_can_filter_by_analysis_scope()
    {
        // Use a unique submitting party to isolate test data
        $unique = 'FilterScopeTest-' . uniqid();

        AdvisoryNote::factory()->create([
            'submitting_party' => $unique . '-full-map',
            'analysis_scope' => 'full map',
            'status' => 'pending',
        ]);
        AdvisoryNote::factory()->create([
            'submitting_party' => $unique . '-geo',
            'analysis_scope' => 'geographic subset',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/adviser/submissions?analysis_scope=full+map');

        $response->assertOk();
        // Should contain the full map record
        $submittingParties = collect($response->json('data'))->pluck('submitting_party');
        $this->assertContains($unique . '-full-map', $submittingParties);
    }

    public function test_can_filter_by_status()
    {
        $unique = 'FilterStatusTest-' . uniqid();

        AdvisoryNote::factory()->create([
            'submitting_party' => $unique . '-pending',
            'analysis_scope' => 'full map',
            'status' => 'pending',
        ]);
        AdvisoryNote::factory()->create([
            'submitting_party' => $unique . '-in_review',
            'analysis_scope' => 'full map',
            'status' => 'in_review',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/adviser/submissions?status=in_review');

        $response->assertOk();
        $submittingParties = collect($response->json('data'))->pluck('submitting_party');
        $this->assertContains($unique . '-in_review', $submittingParties);
        $this->assertNotContains($unique . '-pending', $submittingParties);
    }

    public function test_can_filter_by_analysis_scope_and_status()
    {
        $unique = 'FilterBothTest-' . uniqid();

        AdvisoryNote::factory()->create([
            'submitting_party' => $unique . '-match',
            'analysis_scope' => 'full map',
            'status' => 'pending',
        ]);
        AdvisoryNote::factory()->create([
            'submitting_party' => $unique . '-wrong-status',
            'analysis_scope' => 'full map',
            'status' => 'in_review',
        ]);
        AdvisoryNote::factory()->create([
            'submitting_party' => $unique . '-wrong-scope',
            'analysis_scope' => 'geographic subset',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/adviser/submissions?analysis_scope=full+map&status=pending');

        $response->assertOk();
        $submittingParties = collect($response->json('data'))->pluck('submitting_party');
        $this->assertContains($unique . '-match', $submittingParties);
        $this->assertNotContains($unique . '-wrong-status', $submittingParties);
        $this->assertNotContains($unique . '-wrong-scope', $submittingParties);
    }

    // ----------------------------------------------------------------
    //  Pagination
    // ----------------------------------------------------------------

    public function test_respects_per_page_parameter()
    {
        $unique = 'PaginationTest-' . uniqid();

        AdvisoryNote::factory(10)->create([
            'submitting_party' => $unique,
            'analysis_scope' => 'full map',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/adviser/submissions?per_page=3');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'per_page',
            'total',
        ]);
        $this->assertEquals(3, $response->json('per_page'));
    }

    // ----------------------------------------------------------------
    //  Empty results
    // ----------------------------------------------------------------

    public function test_returns_empty_list_when_filter_matches_none()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/adviser/submissions?status=non_existent_status_xyz');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // ----------------------------------------------------------------
    //  Ordering
    // ----------------------------------------------------------------

    public function test_returns_submissions_ordered_by_submitted_at_desc()
    {
        $unique = 'OrderTest-' . uniqid();

        $old = AdvisoryNote::factory()->create([
            'submitting_party' => $unique . '-old',
            'analysis_scope' => 'full map',
            'status' => 'pending',
            'submitted_at' => now()->subDays(2),
        ]);

        $new = AdvisoryNote::factory()->create([
            'submitting_party' => $unique . '-new',
            'analysis_scope' => 'full map',
            'status' => 'pending',
            'submitted_at' => now()->subDay(),
        ]);

        $latest = AdvisoryNote::factory()->create([
            'submitting_party' => $unique . '-latest',
            'analysis_scope' => 'full map',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/adviser/submissions');

        $response->assertOk();

        // Find our test records in the response and verify their order
        $records = collect($response->json('data'))->filter(function ($item) use ($unique) {
            return str_starts_with($item['submitting_party'], $unique);
        })->values();

        $this->assertCount(3, $records);
        $this->assertEquals($latest->id, $records[0]['id']);
        $this->assertEquals($new->id, $records[1]['id']);
        $this->assertEquals($old->id, $records[2]['id']);
    }
}