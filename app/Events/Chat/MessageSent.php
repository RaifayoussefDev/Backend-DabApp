<?php

namespace App\Events\Chat;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ChatMessage $message,
        public readonly int $sessionId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("chat.{$this->sessionId}");
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'             => $this->message->id,
            'session_id'     => $this->message->session_id,
            'sender_id'      => $this->message->sender_id,
            'sender_type'    => $this->message->sender_type,
            'message'        => $this->message->message,
            'message_type'   => $this->message->message_type,
            'attachment_url' => $this->message->attachment_full_url,
            'is_read'        => $this->message->is_read,
            'created_at'     => $this->message->created_at->toISOString(),
        ];
    }
}
