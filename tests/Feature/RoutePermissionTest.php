<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $coordinatorUser;
    protected User $memberUser;
    protected User $unauthenticatedUser;
    protected Organisation $organisation;
    protected ProgrammeEntry $programmeEntry;

    protected function setUp(): void
    {
        parent::setUp();

        // Exercise the real seeded roles/permissions (nep_admin is
        // is_super_admin) rather than hand-rolled fixtures, so these tests
        // stay honest about what the deployed RBAC configuration allows.
        $this->seed(RolePermissionSeeder::class);

        $this->organisation = Organisation::factory()->create();
        $this->programmeEntry = ProgrammeEntry::factory()->create([
            'organisation_id' => $this->organisation->id,
        ]);

        $this->adminUser = User::factory()->create([
            'organisation_id' => null,
            'role' => 'nep_admin',
        ]);

        $this->coordinatorUser = User::factory()->create([
            'organisation_id' => null,
            'role' => 'nep_coordinator',
        ]);

        $this->memberUser = User::factory()->create([
            'organisation_id' => $this->organisation->id,
            'role' => 'member_org',
        ]);
    }

    // ==================== Public Routes (No Authentication Required) ====================

    public function test_public_routes_are_accessible_without_authentication(): void
    {
        // Login route should be accessible (returns 422 for invalid data, not 401)
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
        // Should return 401 for invalid credentials or 422 for validation, but not auth middleware error
        $response->assertStatus(401);
    }

    // ==================== Authentication Required Routes ====================

    public function test_unauthenticated_user_receives_401_for_protected_routes(): void
    {
        $protectedRoutes = [
            '/api/user',
            '/api/session',
            '/api/notifications',
            '/api/programme-entries',
            '/api/organisations/me',
            '/api/provinces',
            '/api/refdata/education-levels',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->getJson($route);
            $response->assertUnauthorized();
            $response->assertJsonPath('message', 'Unauthenticated.');
        }
    }

    // ==================== Routes Accessible to All Authenticated Users ====================

    public function test_all_authenticated_roles_can_access_common_routes(): void
    {
        $commonRoutes = [
            '/api/user',
            '/api/session',
            '/api/notifications',
            '/api/provinces',
            '/api/refdata/education-levels',
            '/api/refdata/budget-bands',
            '/api/refdata/counterpart-agencies',
        ];

        $roles = [
            'nep_admin' => $this->adminUser,
            'nep_coordinator' => $this->coordinatorUser,
            'member_org' => $this->memberUser,
        ];

        foreach ($roles as $role => $user) {
            foreach ($commonRoutes as $route) {
                $response = $this->actingAs($user)->getJson($route);
                $response->assertStatus(200);
            }
        }
    }

    // ==================== Programme Entry Routes ====================

    public function test_member_org_can_create_programme_entry(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->postJson('/api/programme-entries', [
                'programme_name' => 'Test Programme',
                'start_year' => 2024,
            ]);

        $response->assertStatus(201);
    }

    public function test_nep_admin_can_create_programme_entry(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/programme-entries', [
                'programme_name' => 'Admin Programme',
                'start_year' => 2024,
                'organisation_id' => $this->organisation->id,
            ]);

        $response->assertStatus(201);
    }

    public function test_nep_coordinator_can_create_programme_entry(): void
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->postJson('/api/programme-entries', [
                'programme_name' => 'Coordinator Programme',
                'start_year' => 2024,
                'organisation_id' => $this->organisation->id,
            ]);

        $response->assertStatus(201);
    }

    public function test_member_org_can_view_own_programme_entries(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/programme-entries');

        $response->assertOk();
    }

    public function test_only_nep_admin_can_verify_programme_entry(): void
    {
        $endpoint = "/api/programme-entries/{$this->programmeEntry->id}/verify";

        // Admin should be able to verify (may fail validation but not auth)
        $response = $this->actingAs($this->adminUser)->patchJson($endpoint);
        $response->assertStatus(200);

        // Coordinator should be forbidden — verifying is gated by
        // programmes.verify (permission-based), which coordinator doesn't hold.
        $response = $this->actingAs($this->coordinatorUser)->patchJson($endpoint);
        $response->assertForbidden();
        $response->assertJsonPath('message', 'Forbidden. You do not have the required permissions.');

        // Member should be forbidden
        $response = $this->actingAs($this->memberUser)->patchJson($endpoint);
        $response->assertForbidden();
        $response->assertJsonPath('message', 'Forbidden. You do not have the required permissions.');
    }

    // ==================== Admin-Only Routes ====================

    public function test_admin_user_routes_are_gated_by_the_users_permission_not_role(): void
    {
        // Admin (super admin) has every permission implicitly.
        $response = $this->actingAs($this->adminUser)->getJson('/api/admin/users');
        $response->assertStatus(200);

        // Coordinator holds users.view per the seeded role — can list, per
        // permission, not because of a role-name check.
        $response = $this->actingAs($this->coordinatorUser)->getJson('/api/admin/users');
        $response->assertStatus(200);

        // ...but coordinator does NOT hold users.create — mutating actions
        // stay admin-only even though viewing is allowed.
        $response = $this->actingAs($this->coordinatorUser)->postJson('/api/admin/users', [
            'name' => 'Nope', 'email' => 'nope@test.com', 'role' => 'member_org',
        ]);
        $response->assertForbidden();
        $response->assertJsonPath('message', 'Forbidden. You do not have the required permissions.');

        // Member has neither users.view nor users.create.
        $response = $this->actingAs($this->memberUser)->getJson('/api/admin/users');
        $response->assertForbidden();
        $response->assertJsonPath('message', 'Forbidden. You do not have the required permissions.');
    }

    public function test_admin_organisation_routes_are_gated_by_the_organisations_permission_not_role(): void
    {
        // Admin and coordinator both hold organisations.view per the seeder.
        $response = $this->actingAs($this->adminUser)->getJson('/api/admin/organisations');
        $response->assertStatus(200);

        $response = $this->actingAs($this->coordinatorUser)->getJson('/api/admin/organisations');
        $response->assertStatus(200);

        // Coordinator does not hold organisations.create.
        $response = $this->actingAs($this->coordinatorUser)->postJson('/api/admin/organisations', [
            'name' => 'Nope',
        ]);
        $response->assertForbidden();

        // Member has neither.
        $response = $this->actingAs($this->memberUser)->getJson('/api/admin/organisations');
        $response->assertForbidden();
    }

    public function test_only_nep_admin_can_manage_taxonomy(): void
    {
        // Admin should have access (succeeds with valid data)
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/taxonomy/categories', [
                'code' => 'test',
                'label' => 'Test Category',
            ]);
        $response->assertStatus(201);

        // Coordinator should be forbidden
        $response = $this->actingAs($this->coordinatorUser)
            ->postJson('/api/taxonomy/categories', [
                'code' => 'test2',
                'label' => 'Test Category 2',
            ]);
        $response->assertForbidden();

        // Member should be forbidden
        $response = $this->actingAs($this->memberUser)
            ->postJson('/api/taxonomy/categories', [
                'code' => 'test3',
                'label' => 'Test Category 3',
            ]);
        $response->assertForbidden();
    }

    // ==================== Admin and Coordinator Routes ====================

    public function test_admin_and_coordinator_can_access_dashboard(): void
    {
        $dashboardRoutes = [
            '/api/dashboard/stats',
            '/api/dashboard/recent-activity',
        ];

        // Admin should have access
        foreach ($dashboardRoutes as $route) {
            $response = $this->actingAs($this->adminUser)->getJson($route);
            $response->assertStatus(200);
        }

        // Coordinator should have access
        foreach ($dashboardRoutes as $route) {
            $response = $this->actingAs($this->coordinatorUser)->getJson($route);
            $response->assertStatus(200);
        }

        // Member should be forbidden
        foreach ($dashboardRoutes as $route) {
            $response = $this->actingAs($this->memberUser)->getJson($route);
            $response->assertForbidden();
        }
    }

    public function test_admin_and_coordinator_can_access_map_entries(): void
    {
        $mapRoutes = [
            '/api/map/entries',
            '/api/map/entries/export',
            '/api/map/entries/export/pdf',
            '/api/map/entries/geojson',
        ];

        // Admin should have access
        foreach ($mapRoutes as $route) {
            $response = $this->actingAs($this->adminUser)->getJson($route);
            $response->assertStatus(200);
        }

        // Coordinator should have access
        foreach ($mapRoutes as $route) {
            $response = $this->actingAs($this->coordinatorUser)->getJson($route);
            $response->assertStatus(200);
        }

        // Member can query/export the map (reports.view/export — scoped to
        // their own organisation by BuildsMapQuery — see MapEntryTest for
        // the scoping coverage), matching this endpoint's own documented
        // "member_org sees only their own organisation's entries" behavior.
        foreach (['/api/map/entries', '/api/map/entries/export', '/api/map/entries/export/pdf'] as $route) {
            $response = $this->actingAs($this->memberUser)->getJson($route);
            $response->assertStatus(200);
        }

        // GeoJSON stays a staff-only aggregate visualization (dashboard.view),
        // which member_org does not hold.
        $response = $this->actingAs($this->memberUser)->getJson('/api/map/entries/geojson');
        $response->assertForbidden();
    }

    public function test_admin_and_coordinator_can_manage_policy_documents(): void
    {
        // Admin can create
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/policy-documents', [
                'title' => 'Test Policy',
                'document_type' => 'policy',
                'authority' => 'Test Authority',
                'version' => '1.0',
                'date' => '2024-01-01',
            ]);
        $response->assertStatus(201);

        // Coordinator can also create (both admin and coordinator have access)
        $response = $this->actingAs($this->coordinatorUser)
            ->postJson('/api/policy-documents', [
                'title' => 'Test Policy 2',
                'document_type' => 'policy',
                'authority' => 'Test Authority',
                'version' => '1.0',
                'date' => '2024-01-01',
            ]);
        $response->assertStatus(201);

        // Member should be forbidden from creating
        $response = $this->actingAs($this->memberUser)
            ->postJson('/api/policy-documents', [
                'title' => 'Test Policy',
                'document_type' => 'policy',
                'authority' => 'Test Authority',
                'version' => '1.0',
                'date' => '2024-01-01',
            ]);
        $response->assertForbidden();
    }

    public function test_admin_and_coordinator_can_view_adviser_submissions(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/adviser/submissions');
        $response->assertStatus(200);

        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/adviser/submissions');
        $response->assertStatus(200);

        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/adviser/submissions');
        $response->assertForbidden();
    }

    // ==================== Admin and Member Org Routes ====================

    public function test_admin_and_member_can_manage_programme_activities(): void
    {
        $endpoint = "/api/programme-entries/{$this->programmeEntry->id}/activities";

        // Admin can access
        $response = $this->actingAs($this->adminUser)->getJson($endpoint);
        $response->assertStatus(200);

        // Member can access
        $response = $this->actingAs($this->memberUser)->getJson($endpoint);
        $response->assertStatus(200);

        // Coordinator can access too — this route is gated by
        // permission:programmes.create,programmes.update (see routes/api.php),
        // which nep_coordinator is seeded with, and per that route's own
        // comment, coordinators were already included in the hardcoded role
        // list this replaced. Stale "should be forbidden" expectation fixed.
        $response = $this->actingAs($this->coordinatorUser)->getJson($endpoint);
        $response->assertStatus(200);
    }

    public function test_admin_and_member_can_manage_programme_keywords(): void
    {
        $endpoint = "/api/programme-entries/{$this->programmeEntry->id}/keywords";

        // Admin can access
        $response = $this->actingAs($this->adminUser)
            ->putJson($endpoint, ['keywords' => ['education']]);
        $response->assertStatus(200);

        // Member can access
        $response = $this->actingAs($this->memberUser)
            ->putJson($endpoint, ['keywords' => ['health']]);
        $response->assertStatus(200);

        // Coordinator can access too — see the note in
        // test_admin_and_member_can_manage_programme_activities() above.
        $response = $this->actingAs($this->coordinatorUser)
            ->putJson($endpoint, ['keywords' => ['agriculture']]);
        $response->assertStatus(200);
    }

    public function test_admin_and_member_can_manage_programme_geography(): void
    {
        $endpoint = "/api/programme-entries/{$this->programmeEntry->id}/geography";

        // Admin can access (may fail validation but not auth)
        $response = $this->actingAs($this->adminUser)
            ->putJson($endpoint, [
                'other_countries' => [],
                'provinces' => [],
            ]);
        $response->assertStatus(200);

        // Member can access
        $response = $this->actingAs($this->memberUser)
            ->putJson($endpoint, [
                'other_countries' => [],
                'provinces' => [],
            ]);
        $response->assertStatus(200);

        // Coordinator can access too — see the note in
        // test_admin_and_member_can_manage_programme_activities() above.
        $response = $this->actingAs($this->coordinatorUser)
            ->putJson($endpoint, [
                'other_countries' => [],
                'provinces' => [],
            ]);
        $response->assertStatus(200);
    }

    public function test_admin_and_member_can_manage_government_agreements(): void
    {
        $endpoint = "/api/programme-entries/{$this->programmeEntry->id}/government-agreements";

        // Admin can access (may fail validation but not auth)
        $response = $this->actingAs($this->adminUser)
            ->putJson($endpoint, [
                'agreements' => [],
            ]);
        $response->assertStatus(200);

        // Member can access
        $response = $this->actingAs($this->memberUser)
            ->putJson($endpoint, [
                'agreements' => [],
            ]);
        $response->assertStatus(200);

        // Coordinator can access too — see the note in
        // test_admin_and_member_can_manage_programme_activities() above.
        $response = $this->actingAs($this->coordinatorUser)
            ->putJson($endpoint, [
                'agreements' => [],
            ]);
        $response->assertStatus(200);
    }

    // ==================== Admin, Coordinator, and Member Org Routes ====================

    public function test_all_roles_can_view_geography(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/programme-entries/{$this->programmeEntry->id}/geography");
        $response->assertStatus(200);

        $response = $this->actingAs($this->coordinatorUser)
            ->getJson("/api/programme-entries/{$this->programmeEntry->id}/geography");
        $response->assertStatus(200);

        $response = $this->actingAs($this->memberUser)
            ->getJson("/api/programme-entries/{$this->programmeEntry->id}/geography");
        $response->assertStatus(200);
    }

    public function test_all_roles_can_view_taxonomy_categories(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/taxonomy/categories');
        $response->assertStatus(200);

        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/taxonomy/categories');
        $response->assertStatus(200);

        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/taxonomy/categories');
        $response->assertStatus(200);
    }

    public function test_all_roles_can_view_policy_documents(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/policy-documents');
        $response->assertStatus(200);

        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/policy-documents');
        $response->assertStatus(200);

        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/policy-documents');
        $response->assertStatus(200);
    }

    // ==================== Logout Route ====================

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');
        $response->assertUnauthorized();
    }

    // ==================== Organisation Profile Routes ====================

    public function test_member_org_can_view_own_organisation_profile(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/organisations/me');
        $response->assertStatus(200);
    }

    public function test_member_org_can_update_own_organisation_profile(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->patchJson('/api/organisations/me', [
                'name' => 'Updated Organisation Name',
            ]);
        $response->assertStatus(200);
    }

    public function test_admin_can_access_organisation_profile(): void
    {
        // Admin without organisation_id gets 404, which is expected behavior
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/organisations/me');
        // Admin may not have an organisation, so 404 is acceptable
        $response->assertNotFound();
    }

    // ==================== Notification Routes ====================

    public function test_all_authenticated_users_can_access_notifications(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/notifications');
        $response->assertStatus(200);

        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/notifications');
        $response->assertStatus(200);

        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/notifications');
        $response->assertStatus(200);
    }

    // ==================== Location Routes ====================

    public function test_all_authenticated_users_can_access_locations(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/provinces');
        $response->assertStatus(200);

        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/provinces');
        $response->assertStatus(200);

        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/provinces');
        $response->assertStatus(200);
    }

    // ==================== Admin-Only Location Stats ====================

    public function test_only_admin_and_coordinator_can_view_province_counts(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/provinces/counts');
        $response->assertStatus(200);

        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/provinces/counts');
        $response->assertStatus(200);

        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/provinces/counts');
        $response->assertForbidden();
    }

    // ==================== Admin-Only Taxonomy Stats ====================

    public function test_only_admin_and_coordinator_can_view_category_counts(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/taxonomy/categories/counts');
        $response->assertStatus(200);

        $response = $this->actingAs($this->coordinatorUser)
            ->getJson('/api/taxonomy/categories/counts');
        $response->assertStatus(200);

        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/taxonomy/categories/counts');
        $response->assertForbidden();
    }

    // ==================== Adviser Submission Routes ====================

    public function test_admin_and_coordinator_can_create_adviser_submission(): void
    {
        // Admin can create (may fail validation but not auth)
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Test Party',
                'document_name' => 'Test Document',
            ]);
        $response->assertStatus(201);

        // Coordinator can create
        $response = $this->actingAs($this->coordinatorUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Test Party 2',
                'document_name' => 'Test Document 2',
            ]);
        $response->assertStatus(201);

        // Member should be forbidden
        $response = $this->actingAs($this->memberUser)
            ->postJson('/api/adviser/submissions', [
                'submitting_party' => 'Test Party 3',
                'document_name' => 'Test Document 3',
            ]);
        $response->assertForbidden();
    }

    // ==================== Error Response Format ====================

    public function test_403_response_has_correct_format(): void
    {
        $response = $this->actingAs($this->memberUser)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Forbidden. You do not have the required permissions.',
        ]);
        $response->assertJsonStructure([
            'message',
        ]);
    }

    public function test_401_response_has_correct_format(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
        $response->assertJsonStructure([
            'message',
        ]);
    }
}