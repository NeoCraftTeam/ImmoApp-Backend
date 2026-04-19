<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageSent;
use App\Jobs\SendChatPushNotificationJob;
use App\Jobs\SendOfflineEmailNotificationJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Handles message creation, deletion and history retrieval.
 */
final readonly class MessageService
{
    public function __construct(
        private EncryptionService $encryption,
    ) {}

    /**
     * Send a message in a conversation.
     *
     * Encrypts the body, creates the record, updates conversation metadata,
     * broadcasts the event, and dispatches push/email notification jobs.
     *
     * @param array<int, array<string, mixed>>|null $attachments
     *
     * @throws RuntimeException if sender is not a conversation participant
     */
    public function send(
        Conversation $conv,
        User $sender,
        string $body = '',
        string $type = 'text',
        ?array $attachments = null,
        ?string $replyToId = null,
    ): Message {
        abort_unless(
            in_array($sender->id, [$conv->tenant_id, $conv->landlord_id], true),
            404,
        );

        $encrypted = null;
        $iv        = null;

        if ($body !== '') {
            $result    = $this->encryption->encrypt($body);
            $encrypted = $result['ciphertext'];
            $iv        = $result['iv'];
        }

        $preview = mb_substr(strip_tags($body), 0, 200);

        $message = DB::transaction(function () use (
            $conv, $sender, $encrypted, $iv, $type,
            $attachments, $replyToId, $preview
        ): Message {
            $msg = Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $sender->id,
                'type'            => MessageType::from($type),
                'body'            => $encrypted,
                'body_iv'         => $iv,
                'attachments'     => $attachments,
                'reply_to_id'     => $replyToId,
                'status'          => MessageStatus::Sent,
            ]);

            $conv->update([
                'last_message_at'      => now(),
                'last_message_preview' => $preview,
                'last_message_id'      => $msg->id,
            ]);

            return $msg;
        });

        $message->load(['sender:id,firstname,lastname,avatar', 'replyTo']);

        broadcast(new MessageSent($message))->toOthers();

        $recipientId = $sender->id === $conv->tenant_id
            ? $conv->landlord_id
            : $conv->tenant_id;

        SendChatPushNotificationJob::dispatch($recipientId, $message->id);
        SendOfflineEmailNotificationJob::dispatch($recipientId, $message->id)
            ->delay(now()->addMinutes(5));

        return $message;
    }

    /**
     * Soft-delete a message. Only the sender can delete, and only within 24 hours.
     *
     * @throws RuntimeException on unauthorized access or time limit exceeded
     */
    public function delete(Message $message, User $user): void
    {
        abort_unless($message->sender_id === $user->id, 403);
        abort_if($message->created_at?->diffInHours(now()) >= 24, 422);

        DB::transaction(function () use ($message): void {
            $message->update(['body' => null, 'body_iv' => null]);
            $message->delete();
        });

        broadcast(new MessageDeleted($message->conversation_id, $message->id));
    }

    /**
     * Return cursor-paginated message history for a conversation.
     * Messages are ordered newest-first; client reverses for display.
     *
     * @return CursorPaginator<Message>
     */
    public function getHistory(
        Conversation $conv,
        ?string $cursor = null,
        int $perPage = 30,
    ): CursorPaginator {
        return $conv->messages()
            ->with([
                'sender:id,firstname,lastname,avatar',
                'replyTo:id,body,body_iv,sender_id,deleted_at',
            ])
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }
}
