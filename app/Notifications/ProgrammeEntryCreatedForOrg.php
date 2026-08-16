<?php

namespace App\Notifications;

use App\Models\ProgrammeEntry;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ProgrammeEntryCreatedForOrg extends Notification implements ShouldBroadcastNow
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
        return 'programme.draft.created';
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'notification_id'    => $this->id,
            'type'               => 'programme_draft_created',
            'title'              => 'New programme entry created',
            'programme_entry_id' => $this->entry->id,
            'programme_name'     => $this->entry->programme_name,
            'message'            => "A new programme entry \"" . $this->entry->programme_name . "\" has been created for your organisation.",
        ];
    }
}
