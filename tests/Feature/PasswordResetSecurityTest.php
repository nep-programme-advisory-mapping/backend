<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * POST /forgot-password (BUG-07): must not reveal whether an email is
 * registered, and must be rate-limited.
 */
class PasswordResetSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_is_identical_for_a_registered_and_unregistered_email(): void
    {
        $user = User::factory()->create(['email' => 'registered@test.com']);

        $registered = $this->postJson('/api/forgot-password', ['email' => 'registered@test.com']);
        $unregistered = $this->postJson('/api/forgot-password', ['email' => 'nobody@test.com']);

        $registered->assertStatus(200);
        $unregistered->assertStatus(200);
        $this->assertSame($registered->json('message'), $unregistered->json('message'));
    }

    public function test_reset_link_is_only_actually_sent_for_a_registered_email(): void
    {
        Password::shouldReceive('sendResetLink')->twice()->andReturn(Password::RESET_LINK_SENT);

        User::factory()->create(['email' => 'registered@test.com']);

        $this->postJson('/api/forgot-password', ['email' => 'registered@test.com'])->assertStatus(200);
        $this->postJson('/api/forgot-password', ['email' => 'nobody@test.com'])->assertStatus(200);
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/forgot-password', ['email' => 'nobody@test.com'])->assertStatus(200);
        }

        $this->postJson('/api/forgot-password', ['email' => 'nobody@test.com'])->assertStatus(429);
    }

    /**
     * An SMTP rejection (e.g. a provider bouncing an unaligned From domain)
     * used to surface as an uncaught 500 for existing accounts only, which
     * both breaks the response contract and — combined with a 200 for
     * unregistered emails — reintroduces exactly the account-enumeration
     * signal this endpoint's generic message exists to avoid. See
     * PasswordResetController::forgotPassword().
     */
    public function test_mail_failure_does_not_500_or_change_the_generic_response(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andThrow(
            new \Exception('Expected response code "250" but got code "550", with message "550 5.7.1 unauthenticated senders not allowed"')
        );

        $response = $this->postJson('/api/forgot-password', ['email' => 'registered@test.com']);

        $response->assertStatus(200)
            ->assertJson(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    public function test_mail_failure_is_logged_without_exposing_credentials(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \Exception('SMTP auth failed'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to send password reset email', \Mockery::on(function ($context) {
                return $context['email'] === 'registered@test.com'
                    && $context['error'] === 'SMTP auth failed'
                    && !str_contains(json_encode($context), 'MAIL_PASSWORD')
                    && !isset($context['password']);
            }));

        $this->postJson('/api/forgot-password', ['email' => 'registered@test.com'])->assertStatus(200);
    }
}
