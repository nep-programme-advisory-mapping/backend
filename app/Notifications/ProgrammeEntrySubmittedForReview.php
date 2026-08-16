<?php

namespace App\Notifications;

use App\Models\ProgrammeEntry;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ProgrammeEntrySubmittedForReview extends Notification implements ShouldBroadcast
{
    private ?int $notifiableId = null;

    public function __construct(public ProgrammeEntry $entry) {}

    public function via(object $notifiable): array
    {
        $this->notifiableId = $notifiable->id;
        return ['database', 'broadcast'];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->notifiableId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'programme.submitted';
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'notification_id'    => $this->id,
            'programme_entry_id' => $this->entry->id,
            'programme_name'     => $this->entry->programme_name,
            'organisation_name'  => $this->entry->organisation->name ?? null,
            'message'            => "A programme entry has been submitted by {$this->entry->organisation->name} and is ready for review.",
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'programme_entry_id' => $this->entry->id,
            'programme_name'     => $this->entry->programme_name,
            'organisation_name'  => $this->entry->organisation->name ?? null,
            'message'            => "A programme entry has been submitted by {$this->entry->organisation->name} and is ready for review.",
        ];
    }
}