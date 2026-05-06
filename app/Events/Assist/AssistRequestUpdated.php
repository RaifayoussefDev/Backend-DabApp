<?php

namespace App\Events\Assist;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssistRequestUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $requestId,
        public readonly int    $seekerId,
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("assist.seeker.{$this->seekerId}");
    }

    public function broadcastAs(): string
    {
        return 'request.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->requestId,
            'type'       => $this->type,
            'title'      => $this->title,
            'body'       => $this->body,
        ];
    }
}
