<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SSRF guard on POST /programme-entries/fetch-url (BUG-03).
 */
class ProgrammeActivityAiFetchUrlTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->member = User::factory()->create(['role' => 'member_org']);
    }

    public function test_fetching_a_public_url_succeeds(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><body>' . str_repeat('Readable programme text. ', 10) . '</body></html>', 200),
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/programme-entries/fetch-url', ['url' => 'https://example.com/programme']);

        $response->assertStatus(200);
        $response->assertJsonStructure(['text']);
    }

    #[DataProvider('unsafeUrlProvider')]
    public function test_internal_and_reserved_addresses_are_rejected(string $url): void
    {
        Http::fake(); // any real request means the guard failed — fail loudly if one is attempted

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/programme-entries/fetch-url', ['url' => $url]);

        $response->assertStatus(422);
        Http::assertNothingSent();
    }

    public static function unsafeUrlProvider(): array
    {
        return [
            'cloud metadata endpoint' => ['http://169.254.169.254/latest/meta-data/'],
            'loopback' => ['http://127.0.0.1/admin'],
            'private class A' => ['http://10.0.0.5/internal'],
            'private class C' => ['http://192.168.1.1/'],
            'localhost hostname' => ['http://localhost:6379/'],
            '.internal TLD' => ['http://service.internal/'],
            'IPv6 loopback' => ['http://[::1]/'],
        ];
    }

    public function test_non_http_scheme_is_rejected(): void
    {
        Http::fake();

        // 'url' validation itself rejects most non-http schemes, but this
        // confirms the guard defends in depth rather than only trusting that rule.
        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/programme-entries/fetch-url', ['url' => 'ftp://example.com/file']);

        $response->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_user_without_programmes_permission_is_forbidden(): void
    {
        $bareRole = \App\Models\Role::create(['name' => 'bare', 'display_name' => 'Bare']);
        $bareUser = User::create([
            'name' => 'Bare', 'email' => 'bare@test.com', 'password' => bcrypt('password'),
            'role' => 'bare', 'status' => 'active',
        ]);
        $bareUser->roles()->attach($bareRole);

        $response = $this->actingAs($bareUser, 'sanctum')
            ->postJson('/api/programme-entries/fetch-url', ['url' => 'https://example.com/']);

        $response->assertStatus(403);
    }
}
