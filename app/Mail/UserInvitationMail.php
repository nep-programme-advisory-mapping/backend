<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UserInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $userName;
    public string $userEmail;
    public string $defaultPassword;
    public string $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(string $userName, string $userEmail, string $defaultPassword, string $loginUrl)
    {
        $this->userName = $userName;
        $this->userEmail = $userEmail;
        $this->defaultPassword = $defaultPassword;
        $this->loginUrl = $loginUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to the NEP System',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invitation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Now that sending is queued (see the ShouldQueue interface above), an
     * SMTP failure happens in the queue worker process, after the request
     * that created the user has already returned — the controller's own
     * try/catch around Mail::send() only catches dispatch-time failures, not
     * this. Without this hook the failure would only be visible via
     * `php artisan queue:failed`, which is easy to miss. Deliberately omits
     * $defaultPassword/credentials from the log.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Invitation email failed to send (queued job)', [
            'user_email' => $this->userEmail,
            'error' => $exception->getMessage(),
        ]);
    }
}