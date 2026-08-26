<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageReceived;
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
use Illuminate\Support\Facades\Log;
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
     * The client-sealed (E2EE) branch is gated by `config('chat.client_sealed_enabled')`,
     * which defaults to `false` since May 2026 for cross-device portability. When the
     * flag is `false`, any `$e2ee` payload is silently ignored and the server falls
     * back to the standard server-side encryption branch (a debug warning is logged so
     * that mismatched clients can be tracked).
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
            'Impossible d\'envoyer un message dans une conversation archivée.',
        );

        // Defence-in-depth: reject any attachment whose storage path doesn't
        // belong to this conversation. Form Request validates structure;
        // here we validate ownership against the conversation prefix on R2.
        if ($attachments !== null) {
            foreach ($attachments as $attachment) {
                $url = $attachment['url'] ?? null;
                if (!is_string($url) || !$this->attachments->belongsToConversation($url, $conv->id)) {
                    abort(422, 'Pièce jointe invalide pour cette conversation.');
                }
            }
        }

        // Cross-device portability: when client-sealed (E2EE) is disabled, drop
        // any incoming $e2ee payload and let the server-encrypted branch run.
        // We log a debug warning so that a frontend still wired for sealed sends
        // can be spotted, but we never fail the request — the user expects their
        // message to go through.
        if ($e2ee !== null && !config('chat.client_sealed_enabled', false)) {
            Log::warning('chat.send: client-sealed payload received while the feature is disabled; falling back to server-encrypted send.', [
                'conversation_id' => $conv->id,
                'sender_id' => $sender->id,
            ]);
            $e2ee = null;
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
                    'Les deux participants doivent enregistrer une clé publique E2EE avant d\'envoyer des messages chiffrés.',
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
                        'Le premier message chiffré de cette conversation doit inclure e2ee_wrapped_keys pour les deux participants.',
                    );
                    $conv->update([
                        'e2ee_wrapped_key_tenant' => $wk['tenant'],
                        'e2ee_wrapped_key_landlord' => $wk['landlord'],
                    ]);
                } elseif (isset($e2ee['wrapped_keys']) && is_array($e2ee['wrapped_keys'])) {
                    abort(422, 'Cette conversation possède déjà des clés de session E2EE ; omettez e2ee_wrapped_keys.');
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

        $message->load(['sender:id,firstname,lastname,avatar', 'sender.media', 'replyTo']);

        $recipientId = $sender->id === $conv->tenant_id
            ? $conv->landlord_id
            : $conv->tenant_id;

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable) {
            // Reverb may be unavailable in local dev — do not fail the HTTP response
        }

        try {
            // Signal temps réel sur le canal personnel du destinataire :
            // toast + badge + inbox live même hors conversation ouverte
            // (web et mobile), y compris pour une conversation toute neuve.
            // try/catch dédié : un échec de MessageSent ne doit pas le faire taire.
            broadcast(new MessageReceived($message, (string) $recipientId));
        } catch (\Throwable) {
            // Reverb may be unavailable — never fail the HTTP response
        }

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
                'sender.media',
                ChatE2eeSchema::messageReplyToEagerLoadSpec(),
                'reactions:id,message_id,user_id,emoji',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }
}
