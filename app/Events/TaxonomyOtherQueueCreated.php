<?php

namespace App\Events;

use App\Models\TaxonomyOtherQueue;
// use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaxonomyOtherQueueCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public TaxonomyOtherQueue $queueEntry) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('nep-admin')];
    }

    public function broadcastAs(): string
    {
        return 'other.queue.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->queueEntry->id,
            'other_text' => $this->queueEntry->other_text,
            'programme_entry_id' => $this->queueEntry->programme_entry_id,
        ];
    }
}