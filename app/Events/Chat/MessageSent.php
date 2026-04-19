<?php

declare(strict_types=1);

namespace App\Events\Chat;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a new message is sent.
 * Implements ShouldBroadcastNow to bypass the queue for immediate delivery.
 *
 * SECURITY: Never include body_iv, raw encrypted body, or sender email/phone.
 */
final class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Message $message) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->message->conversation_id}")];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'uuid'              => $this->message->id,
            'conversation_uuid' => $this->message->conversation_id,
            'sender_id'         => $this->message->sender_id,
            'sender'            => $sender ? [
                'id'     => $sender->id,
                'name'   => trim("{$sender->firstname} {$sender->lastname}"),
                'avatar' => $sender->avatar,
            ] : null,
            'type'              => $this->message->type->value,
            'body'              => $this->message->decrypted_body,
            'attachments'       => $this->message->attachments,
            'reply_to'          => $this->buildReplyTo(),
            'status'            => $this->message->status->value,
            'created_at'        => $this->message->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function buildReplyTo(): ?array
    {
        $reply = $this->message->replyTo;
        if ($reply === null) {
            return null;
        }

        return [
            'uuid'      => $reply->id,
            'body'      => $reply->decrypted_body !== null
                ? mb_substr($reply->decrypted_body, 0, 80)
                : null,
            'sender_id' => $reply->sender_id,
        ];
    }
}
