<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'role:nep_admin'])->get('/test-admin', function () {
            return response()->json(['message' => 'welcome admin']);
        });
        Route::middleware(['api', 'auth:sanctum', 'role:nep_coordinator'])->get('/test-coordinator', function () {
            return response()->json(['message' => 'welcome coordinator']);
        });
        Route::middleware(['api', 'auth:sanctum', 'role:member_org'])->get('/test-member', function () {
            return response()->json(['message' => 'welcome member']);
        });
    }

    public function test_unauthenticated_requests_are_always_rejected()
    {
        $response = $this->getJson('/test-admin');
        $response->assertStatus(401);
    }

    public function test_nep_admin_can_access_admin_route()
    {
        $user = User::factory()->create(['role' => 'nep_admin']);
        $response = $this->actingAs($user, 'sanctum')->getJson('/test-admin');
        $response->assertStatus(200)
                 ->assertJson(['message' => 'welcome admin']);
    }

    public function test_nep_admin_cannot_access_coordinator_route()
    {
        $user = User::factory()->create(['role' => 'nep_admin']);
        $response = $this->actingAs($user, 'sanctum')->getJson('/test-coordinator');
        $response->assertStatus(403);
    }

    public function test_nep_coordinator_can_access_coordinator_route()
    {
        $user = User::factory()->create(['role' => 'nep_coordinator']);
        $response = $this->actingAs($user, 'sanctum')->getJson('/test-coordinator');
        $response->assertStatus(200)
                 ->assertJson(['message' => 'welcome coordinator']);
    }

    public function test_nep_coordinator_cannot_access_admin_route()
    {
        $user = User::factory()->create(['role' => 'nep_coordinator']);
        $response = $this->actingAs($user, 'sanctum')->getJson('/test-admin');
        $response->assertStatus(403);
    }

    public function test_member_org_can_access_member_route()
    {
        $user = User::factory()->create(['role' => 'member_org']);
        $response = $this->actingAs($user, 'sanctum')->getJson('/test-member');
        $response->assertStatus(200)
                 ->assertJson(['message' => 'welcome member']);
    }

    public function test_member_org_cannot_access_admin_route()
    {
        $user = User::factory()->create(['role' => 'member_org']);
        $response = $this->actingAs($user, 'sanctum')->getJson('/test-admin');
        $response->assertStatus(403);
    }
}
