<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;

class ResetPasswordNotification extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $resetUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Reset Your NEP System Password')
            ->view('emails.reset-password', [
                'resetUrl'  => $resetUrl,
                'userName'  => $notifiable->name,
                'expiresIn' => config('auth.passwords.users.expire', 60),
            ]);
    }

    /**
     * Now that sending is queued, an SMTP failure happens in the queue
     * worker, after PasswordResetController::forgotPassword() has already
     * returned its generic response — that controller's own try/catch only
     * covers dispatch-time failures, not this. Without this hook the
     * failure would only surface via `php artisan queue:failed`. Never logs
     * the reset token itself.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Password reset email failed to send (queued job)', [
            'error' => $exception->getMessage(),
        ]);
    }
}
