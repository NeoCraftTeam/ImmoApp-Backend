<?php

declare(strict_types=1);

namespace App\Events\Chat;

use App\Models\Message;
use App\Services\Chat\AttachmentService;
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
 * SECURITY: Never include server-side IV in ways that help offline attacks.
 * Client-sealed (E2EE): only `e2ee` ciphertext is broadcast — never plaintext.
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

        $isSealed = $this->message->is_client_sealed;

        return [
            'uuid' => $this->message->id,
            'conversation_uuid' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender' => $sender ? [
                'id' => $sender->id,
                'name' => trim("{$sender->firstname} {$sender->lastname}"),
                'avatar' => $sender->resolveChatAvatarUrl(),
            ] : null,
            'type' => $this->message->type->value,
            'body' => $isSealed ? null : $this->message->decrypted_body,
            'e2ee' => $isSealed ? [
                'ciphertext_b64' => $this->message->body,
                'iv_b64' => $this->message->body_iv,
            ] : null,
            'is_client_sealed' => $isSealed,
            'attachments' => app(AttachmentService::class)->refreshSignedUrlsInAttachments($this->message->attachments),
            'reply_to' => $this->buildReplyTo(),
            'status' => $this->message->status->value,
            'read_at' => $this->message->read_at?->toIso8601String(),
            'deleted_at' => $this->message->deleted_at?->toIso8601String(),
            'created_at' => $this->message->created_at?->toIso8601String(),
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
            'uuid' => $reply->id,
            'body' => $reply->is_client_sealed
                ? null
                : ($reply->decrypted_body !== null
                    ? mb_substr($reply->decrypted_body, 0, 80)
                    : null),
            'sender_id' => $reply->sender_id,
            'is_client_sealed' => $reply->is_client_sealed,
        ];
    }
}
