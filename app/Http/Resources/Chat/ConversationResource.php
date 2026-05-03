<?php

declare(strict_types=1);

namespace App\Http\Resources\Chat;

use App\Models\Conversation;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * API resource for a conversation item in the list or detail view.
 *
 * @mixin Conversation
 */
final class ConversationResource extends JsonResource
{
    /**
     * Safe ISO-8601 formatting for timestamps that may arrive as Carbon, string,
     * or null (e.g. partial selects, legacy rows, serializer edge cases).
     */
    private function toIso8601OrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->toIso8601String();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        /** @var Conversation $conv */
        $conv = $this->resource;

        /** @var User|null $user */
        $user = $request->user();
        $other = $user ? $conv->otherParticipant($user) : null;
        $last = $conv->previewMessage;

        return [
            'uuid' => $conv->id,
            'status' => $conv->status->value,
            'last_message_at' => $this->toIso8601OrNull($last?->created_at ?? $conv->last_message_at),
            'unread_count' => $conv->computed_unread_count ?? ($user ? $conv->unreadCountFor($user) : 0),
            'ad' => $conv->ad ? [
                'id' => $conv->ad->id,
                'slug' => $conv->ad->slug,
                'title' => $conv->ad->title,
                'cover_image' => $conv->ad->getFirstMediaUrl('images') ?: null,
            ] : null,
            'other_participant' => $other ? [
                'id' => $other->id,
                'name' => trim("{$other->firstname} {$other->lastname}"),
                'avatar' => $other->resolveChatAvatarUrl(),
                // ISO-8601 timestamp of last authenticated activity. Frontend
                // pairs this with the Pusher presence channel to render
                // "Vu il y a X" when the user is offline.
                'last_seen_at' => $this->toIso8601OrNull($other->last_seen_at),
                'e2ee_public_key_pem' => $other->chat_e2ee_public_key_pem,
            ] : null,
            'e2ee' => [
                'both_keys_registered' => $conv->tenant?->chat_e2ee_public_key_pem
                    && $conv->landlord?->chat_e2ee_public_key_pem,
                'session_ready' => $conv->e2ee_wrapped_key_tenant && $conv->e2ee_wrapped_key_landlord,
                'tenant_public_key_pem' => $conv->tenant?->chat_e2ee_public_key_pem,
                'landlord_public_key_pem' => $conv->landlord?->chat_e2ee_public_key_pem,
                'wrapped_conversation_key_b64' => $user === null
                    ? null
                    : (
                        $user->id === $conv->tenant_id
                        ? $conv->e2ee_wrapped_key_tenant
                        : (
                            $user->id === $conv->landlord_id
                            ? $conv->e2ee_wrapped_key_landlord
                            : null
                        )
                    ),
            ],
            'last_message' => $last ? [
                'uuid' => $last->id,
                'sender_id' => $last->sender_id,
                'body' => $last->is_client_sealed
                    ? null
                    : ($last->decrypted_body !== null
                        ? mb_substr($last->decrypted_body, 0, 80)
                        : null),
                'is_client_sealed' => $last->is_client_sealed,
                'type' => $last->type->value,
                'sent_at' => $this->toIso8601OrNull($last->created_at),
            ] : null,
        ];
    }
}
