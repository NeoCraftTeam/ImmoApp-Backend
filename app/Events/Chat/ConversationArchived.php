<?php

declare(strict_types=1);

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a conversation is archived by a participant.
 *
 * Both participants receive the event via the private conversation channel
 * (broadcasted with toOthers() so the sender does not receive their own
 * action). The recipient's UI removes the conversation from their list /
 * shows the archived banner without a refetch.
 */
final class ConversationArchived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $archivedById,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'conversation.archived';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'conversation_uuid' => $this->conversationId,
            'archived_by_id' => $this->archivedById,
            'archived_at' => now()->toIso8601String(),
        ];
    }
}
