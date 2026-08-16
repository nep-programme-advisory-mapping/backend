<?php

namespace App\Notifications;

use App\Models\AdvisoryNote;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AdviceDelivered extends Notification implements ShouldBroadcastNow
{
    private ?int $notifiableId = null;

    public function __construct(
        public readonly AdvisoryNote $submission,
        public readonly string $coordinatorName
    ) {}

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
        return 'advice.delivered';
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    private function payload(): array
    {
        return [
            'notification_id'    => $this->id,
            'type'               => 'advice_delivered',
            'title'              => 'Coordination advice delivered',
            'advisory_note_id'   => $this->submission->id,
            'programme_entry_id' => $this->submission->programme_entry_id,
            'message'            => "\"{$this->submission->document_name}\" has been advised by {$this->coordinatorName}.",
        ];
    }
}
