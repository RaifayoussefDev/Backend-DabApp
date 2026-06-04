<?php

namespace App\Events\Chat;

use App\Models\ChatSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatSessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ChatSession $session,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.user.{$this->session->user_id}"),
            new PrivateChannel("chat.provider.{$this->session->provider_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.started';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id'  => $this->session->id,
            'booking_id'  => $this->session->booking_id,
            'provider_id' => $this->session->provider_id,
            'user_id'     => $this->session->user_id,
            'status'      => $this->session->session_status,
            'started_at'  => $this->session->started_at?->toISOString(),
        ];
    }
}
