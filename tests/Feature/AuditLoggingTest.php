<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PolicyDocument;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every destructive (delete) admin action must leave a row in the audit
 * trail — see App\Services\AuditLogger and its call sites.
 */
class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['organisation_id' => null, 'role' => 'nep_admin']);
    }

    public function test_deleting_a_user_writes_an_audit_log_entry(): void
    {
        $target = User::factory()->create(['role' => 'member_org']);

        $response = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/admin/users/{$target->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'User',
            'auditable_id' => $target->id,
            'action' => 'deleted',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_deleting_a_role_writes_an_audit_log_entry(): void
    {
        $role = Role::create([
            'name' => 'temp_role',
            'display_name' => 'Temp Role',
            'is_system' => false,
            'is_super_admin' => false,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'Role',
            'auditable_id' => $role->id,
            'action' => 'deleted',
        ]);
    }

    public function test_deleting_a_policy_document_writes_an_audit_log_entry(): void
    {
        $document = PolicyDocument::create([
            'title' => 'Old Policy', 'authority' => 'Test', 'version' => '1.0', 'date' => '2026-01-01',
            'file_name' => 'policy.pdf',
            'mime_type' => 'application/pdf',
            'file_data' => base64_encode('%PDF-1.4 fake pdf bytes'),
            'file_size' => 20,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/policy-documents/{$document->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'PolicyDocument',
            'auditable_id' => $document->id,
            'action' => 'deleted',
        ]);
    }

    public function test_failed_delete_does_not_leave_an_orphan_audit_log_entry(): void
    {
        // Deleting yourself is rejected before any deletion happens — no log should be written.
        $response = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/admin/users/{$this->admin->id}");

        $response->assertStatus(422);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_can_list_audit_logs(): void
    {
        $target = User::factory()->create(['role' => 'member_org']);
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/admin/users/{$target->id}");

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/audit-logs');

        $response->assertStatus(200);
        $response->assertJsonFragment(['auditable_type' => 'User']);
    }

    public function test_non_admin_cannot_list_audit_logs(): void
    {
        $coordinator = User::factory()->create(['organisation_id' => null, 'role' => 'nep_coordinator']);

        $response = $this->actingAs($coordinator, 'sanctum')->getJson('/api/admin/audit-logs');

        $response->assertStatus(403);
    }
}
