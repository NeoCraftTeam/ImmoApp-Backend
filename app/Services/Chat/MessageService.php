<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageSent;
use App\Jobs\SendChatPushNotificationJob;
use App\Jobs\SendOfflineEmailNotificationJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\ChatE2eeSchema;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Handles message creation, deletion and history retrieval.
 */
final readonly class MessageService
{
    public function __construct(
        private EncryptionService $encryption,
        private ConversationService $conversations,
        private AttachmentService $attachments,
    ) {}

    /**
     * Send a message in a conversation.
     *
     * Default: server-side AES encryption (CHAT_ENCRYPTION_KEY). Optional `$e2ee`:
     * client-sealed AES-GCM ciphertext — the server never decrypts or logs plaintext.
     *
     * @param  array<int, array<string, mixed>>|null  $attachments
     * @param  array{ciphertext_b64: string, iv_b64: string, wrapped_keys?: array<string, string>|null}|null  $e2ee
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
        ?array $e2ee = null,
    ): Message {
        abort_unless($sender->id === $conv->tenant_id || $sender->id === $conv->landlord_id, 404);

        abort_if(
            $conv->status === ConversationStatus::Archived,
            422,
            'Cannot send messages to an archived conversation.',
        );

        // Defence-in-depth: reject any attachment whose storage path doesn't
        // belong to this conversation. Form Request validates structure;
        // here we validate ownership against the conversation prefix on R2.
        if ($attachments !== null) {
            foreach ($attachments as $attachment) {
                $url = $attachment['url'] ?? null;
                if (!is_string($url) || !$this->attachments->belongsToConversation($url, $conv->id)) {
                    abort(422, 'Invalid attachment for this conversation.');
                }
            }
        }

        $message = DB::transaction(function () use (
            $conv, $sender, $body, $type, $attachments, $replyToId, $e2ee
        ): Message {
            // Acquire a row-level lock on the conversation so two concurrent
            // sends cannot interleave the {Message::create -> conv->update}
            // pair and leave `last_message_id` / `last_message_at` pointing at
            // the older message.
            $locked = Conversation::query()
                ->whereKey($conv->id)
                ->lockForUpdate()
                ->first();

            if ($locked !== null) {
                $conv->setRawAttributes($locked->getAttributes(), true);
            }

            if ($e2ee !== null) {
                if (!ChatE2eeSchema::e2eeFullyMigrated()) {
                    throw new HttpResponseException(
                        ApiResponse::error(
                            'La base de données doit être migrée pour le chiffrement E2EE des messages. Exécutez : php artisan migrate',
                            503,
                        ),
                    );
                }

                $conv->load(['tenant', 'landlord']);

                abort_if(
                    $conv->tenant?->chat_e2ee_public_key_pem === null
                    || $conv->tenant->chat_e2ee_public_key_pem === ''
                    || $conv->landlord?->chat_e2ee_public_key_pem === null
                    || $conv->landlord->chat_e2ee_public_key_pem === '',
                    422,
                    'Both chat participants must register an E2EE public key before sending sealed messages.',
                );

                $needsKeys = $conv->e2ee_wrapped_key_tenant === null
                    || $conv->e2ee_wrapped_key_tenant === ''
                    || $conv->e2ee_wrapped_key_landlord === null
                    || $conv->e2ee_wrapped_key_landlord === '';

                if ($needsKeys) {
                    $wk = $e2ee['wrapped_keys'] ?? null;
                    abort_if(
                        !is_array($wk)
                        || !array_key_exists('tenant', $wk)
                        || !array_key_exists('landlord', $wk)
                        || $wk['tenant'] === ''
                        || $wk['landlord'] === '',
                        422,
                        'The first sealed message in this conversation must include e2ee_wrapped_keys for both participants.',
                    );
                    $conv->update([
                        'e2ee_wrapped_key_tenant' => $wk['tenant'],
                        'e2ee_wrapped_key_landlord' => $wk['landlord'],
                    ]);
                } elseif (isset($e2ee['wrapped_keys']) && is_array($e2ee['wrapped_keys'])) {
                    abort(422, 'This conversation already has E2EE session keys; omit e2ee_wrapped_keys.');
                }

                $attrs = [
                    'conversation_id' => $conv->id,
                    'sender_id' => $sender->id,
                    'type' => MessageType::Text,
                    'body' => $e2ee['ciphertext_b64'],
                    'body_iv' => $e2ee['iv_b64'],
                    'attachments' => null,
                    'reply_to_id' => $replyToId,
                    'status' => MessageStatus::Sent,
                ];
                if (ChatE2eeSchema::messageClientSealedColumnExists()) {
                    $attrs['is_client_sealed'] = true;
                }
                $msg = Message::create($attrs);
            } else {
                $encrypted = null;
                $iv = null;

                if ($body !== '') {
                    $result = $this->encryption->encrypt($body);
                    $encrypted = $result['ciphertext'];
                    $iv = $result['iv'];
                }

                $attrs = [
                    'conversation_id' => $conv->id,
                    'sender_id' => $sender->id,
                    'type' => MessageType::from($type),
                    'body' => $encrypted,
                    'body_iv' => $iv,
                    'attachments' => $attachments,
                    'reply_to_id' => $replyToId,
                    'status' => MessageStatus::Sent,
                ];
                if (ChatE2eeSchema::messageClientSealedColumnExists()) {
                    $attrs['is_client_sealed'] = false;
                }
                $msg = Message::create($attrs);
            }

            $conv->update([
                'last_message_at' => now(),
                'last_message_preview' => null,
                'last_message_id' => $msg->id,
            ]);

            return $msg;
        });

        $message->load(['sender:id,firstname,lastname,avatar', 'replyTo']);

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable) {
            // Reverb may be unavailable in local dev — do not fail the HTTP response
        }

        $recipientId = $sender->id === $conv->tenant_id
            ? $conv->landlord_id
            : $conv->tenant_id;

        $this->conversations->invalidateUnreadCache($recipientId);

        SendChatPushNotificationJob::dispatch($recipientId, $message->id);
        SendOfflineEmailNotificationJob::dispatch($recipientId, $message->id)
            ->delay(now()->addMinutes(5));

        return $message;
    }

    /**
     * Soft-delete a message. Only the sender can delete, and only within 24 hours.
     *
     * If the deleted message was the conversation's last_message, we realign
     * last_message_id to the next non-deleted message in the same conversation
     * (or null) inside the same transaction. Otherwise the conversation list
     * would render last_message=null while last_message_id still points at a
     * soft-deleted row.
     *
     * @throws RuntimeException on unauthorized access or time limit exceeded
     */
    public function delete(Message $message, User $user): void
    {
        abort_unless($message->sender_id === $user->id, 403);
        abort_if($message->created_at?->diffInHours(now()) >= 24, 422);

        $conversationId = $message->conversation_id;
        $messageId = $message->id;

        DB::transaction(function () use ($message, $conversationId, $messageId): void {
            $message->update(['body' => null, 'body_iv' => null]);
            $message->delete();

            // Realign conversation.last_message_id if the deleted row was the
            // current pointer. Pick the most recent non-deleted message, or null.
            $conversation = Conversation::query()
                ->whereKey($conversationId)
                ->lockForUpdate()
                ->first();

            if ($conversation === null) {
                return;
            }

            if ($conversation->last_message_id !== $messageId) {
                return;
            }

            $nextLatest = Message::query()
                ->where('conversation_id', $conversationId)
                ->whereNull('deleted_at')
                ->latest('created_at')
                ->latest('id')
                ->first();

            $conversation->update([
                'last_message_id' => $nextLatest?->id,
                'last_message_at' => $nextLatest?->created_at,
            ]);
        });

        try {
            broadcast(new MessageDeleted($conversationId, $messageId))->toOthers();
        } catch (\Throwable) {
            // Reverb may be unavailable in local dev — do not fail the HTTP response
        }
    }

    /**
     * Return cursor-paginated message history for a conversation.
     * Messages are ordered newest-first; client reverses for display.
     *
     * @return CursorPaginator<int, Message>
     */
    public function getHistory(
        Conversation $conv,
        ?string $cursor = null,
        int $perPage = 30,
    ): CursorPaginator {
        return $conv->messages()
            ->with([
                'sender:id,firstname,lastname,avatar',
                ChatE2eeSchema::messageReplyToEagerLoadSpec(),
                'reactions:id,message_id,user_id,emoji',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }
}
