<?php

namespace Tests\Unit;

use App\Mail\UserInvitationMail;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * UserInvitationMail and ResetPasswordNotification now implement ShouldQueue
 * (see EMAIL_DELIVERABILITY.md) — an SMTP failure happens in the queue
 * worker, after the triggering request has already returned, so the
 * controllers' own try/catch blocks around Mail::send()/Password::
 * sendResetLink() can no longer catch it. Laravel invokes each class's
 * failed() method instead once retries are exhausted; these tests confirm
 * that hook actually logs rather than failing silently, and never logs the
 * temporary password or reset token.
 */
class QueuedMailFailureLoggingTest extends TestCase
{
    public function test_invitation_mail_implements_should_queue(): void
    {
        $mail = new UserInvitationMail('Jane Doe', 'jane@example.com', 'temp-pass-123', 'https://example.test/login');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $mail);
    }

    public function test_invitation_mail_failure_is_logged_without_the_password(): void
    {
        $mail = new UserInvitationMail('Jane Doe', 'jane@example.com', 'temp-pass-123', 'https://example.test/login');

        Log::shouldReceive('error')
            ->once()
            ->with('Invitation email failed to send (queued job)', \Mockery::on(function ($context) {
                return $context['user_email'] === 'jane@example.com'
                    && $context['error'] === 'SMTP connection refused'
                    && !str_contains(json_encode($context), 'temp-pass-123');
            }));

        $mail->failed(new \Exception('SMTP connection refused'));
    }

    public function test_reset_password_notification_implements_should_queue(): void
    {
        $notification = new ResetPasswordNotification('some-reset-token');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
    }

    public function test_reset_password_notification_failure_is_logged_without_the_token(): void
    {
        $notification = new ResetPasswordNotification('super-secret-reset-token');

        Log::shouldReceive('error')
            ->once()
            ->with('Password reset email failed to send (queued job)', \Mockery::on(function ($context) {
                return $context['error'] === 'SMTP connection refused'
                    && !str_contains(json_encode($context), 'super-secret-reset-token');
            }));

        $notification->failed(new \Exception('SMTP connection refused'));
    }
}
