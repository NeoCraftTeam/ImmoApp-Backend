<?php

declare(strict_types=1);

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a participant marks messages as read.
 *
 * Uses ShouldBroadcast (queued) — NOT ShouldBroadcastNow — because the
 * sender of markAsRead does not need to wait for Reverb's HTTP ACK to
 * complete the request. The recipient's read-receipt tick turning blue
 * a few hundred ms later is imperceptible, and the queue worker picks
 * the job up within ~50ms in normal conditions. This avoids 500-1500ms
 * of synchronous HTTP latency in the markAsRead request lifecycle (the
 * cause of Nightwatch slow-request alerts on PATCH /conversations/{uuid}/read).
 */
final class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $readerId,
        public readonly string $readAt,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'reader_id' => $this->readerId,
            'read_at' => $this->readAt,
        ];
    }
}
