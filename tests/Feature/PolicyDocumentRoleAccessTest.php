<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Policy document routes are still gated by the legacy `role:` middleware
 * (routes/api.php notes this as pending migration to permission-based
 * gating, unlike every other resource in the app). RoutePermissionTest
 * already covers list/create; this fills the gap on update/delete, which
 * had no coverage of their role restriction at all — only
 * PolicyDocumentSecurityTest (file-type/MIME spoofing) and AuditLoggingTest
 * (the audit-log side effect of a successful delete) touch this endpoint.
 */
class PolicyDocumentRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $coordinator;
    private User $member;
    private PolicyDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'nep_admin']);
        $this->coordinator = User::factory()->create(['role' => 'nep_coordinator']);
        $this->member = User::factory()->create(['role' => 'member_org']);

        $this->document = PolicyDocument::create([
            'title' => 'Original Title',
            'authority' => 'MoEYS',
            'version' => '1.0',
            'date' => '2026-01-01',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_update_a_policy_document(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/policy-documents/{$this->document->id}", ['title' => 'Updated by admin']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('policy_documents', ['id' => $this->document->id, 'title' => 'Updated by admin']);
    }

    public function test_coordinator_can_update_a_policy_document(): void
    {
        $response = $this->actingAs($this->coordinator, 'sanctum')
            ->patchJson("/api/policy-documents/{$this->document->id}", ['title' => 'Updated by coordinator']);

        $response->assertStatus(200);
    }

    public function test_member_org_cannot_update_a_policy_document(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->patchJson("/api/policy-documents/{$this->document->id}", ['title' => 'Should be blocked']);

        $response->assertForbidden();
        $this->assertDatabaseHas('policy_documents', ['id' => $this->document->id, 'title' => 'Original Title']);
    }

    public function test_only_admin_can_delete_a_policy_document(): void
    {
        // Coordinator can create/update but must not be able to delete.
        $response = $this->actingAs($this->coordinator, 'sanctum')
            ->deleteJson("/api/policy-documents/{$this->document->id}");
        $response->assertForbidden();
        $this->assertDatabaseHas('policy_documents', ['id' => $this->document->id]);

        // Member org has no write access at all here.
        $response = $this->actingAs($this->member, 'sanctum')
            ->deleteJson("/api/policy-documents/{$this->document->id}");
        $response->assertForbidden();
        $this->assertDatabaseHas('policy_documents', ['id' => $this->document->id]);

        // Admin is the only role wired to the delete route.
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/policy-documents/{$this->document->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('policy_documents', ['id' => $this->document->id]);
    }

    public function test_unauthenticated_user_cannot_update_or_delete_a_policy_document(): void
    {
        $this->patchJson("/api/policy-documents/{$this->document->id}", ['title' => 'x'])
            ->assertUnauthorized();

        $this->deleteJson("/api/policy-documents/{$this->document->id}")
            ->assertUnauthorized();
    }

    public function test_updating_a_non_existent_policy_document_returns_404(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/policy-documents/999999', ['title' => 'x']);

        $response->assertStatus(404);
    }
}
