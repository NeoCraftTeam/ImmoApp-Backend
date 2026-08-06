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
 * « Nouveau message » diffusé sur le canal privé du DESTINATAIRE
 * (`user.{id}`) — complément de {@see MessageSent} qui n'atteint que les
 * clients déjà abonnés au canal de la conversation.
 *
 * Permet au web et aux apps mobiles de mettre à jour la boîte de
 * réception, le badge non-lu et d'afficher un toast en temps réel, y
 * compris pour les conversations auxquelles le client n'est pas abonné
 * (notamment les conversations toutes neuves).
 *
 * Le corps est tronqué (120 car.) : ce canal sert de signal/preview,
 * le contenu complet reste livré par MessageSent + l'API.
 */
final class MessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly string $recipientId,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->recipientId}")];
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;
        $isSealed = $this->message->is_client_sealed;
        $body = $isSealed ? null : $this->message->decrypted_body;

        return [
            'uuid' => $this->message->id,
            'conversation_uuid' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender' => $sender !== null ? [
                'id' => $sender->id,
                'name' => trim("{$sender->firstname} {$sender->lastname}"),
                'avatar' => $sender->resolveChatAvatarUrl(),
            ] : null,
            'type' => $this->message->type->value,
            'body' => $body !== null ? mb_substr($body, 0, 120) : null,
            'is_client_sealed' => $isSealed,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
