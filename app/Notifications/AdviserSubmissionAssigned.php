<?php

namespace App\Notifications;

use App\Models\AdvisoryNote;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AdviserSubmissionAssigned extends Notification implements ShouldBroadcastNow
{
    private ?int $notifiableId = null;
    public function __construct(public readonly AdvisoryNote $submission) {}

    public function via(object $notifiable): array
    {
        $this->notifiableId = $notifiable->id;
        return ['database', 'broadcast'];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->notifiableId)];
    }

    public function broadcastAs(): string
    {
        return 'adviser.assigned';
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'notification_id'    => $this->id,
            'type'               => 'adviser_submission_assigned',
            'title'              => 'Adviser submission assigned',
            'advisory_note_id'   => $this->submission->id,
            'submitting_party'   => $this->submission->submitting_party,
            'document_name'      => $this->submission->document_name,
            'message'            => "You have been assigned to review \"{$this->submission->document_name}\" from {$this->submission->submitting_party}.",
        ];
    }
}
