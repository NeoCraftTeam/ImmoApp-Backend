<?php

declare(strict_types=1);

namespace App\Http\Resources\Chat;

use App\Models\Message;
use App\Services\Chat\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * API resource for a single message.
 *
 * SECURITY: Server-side sealed messages use `body`+`body_iv` as opaque client ciphertext
 * (E2EE). Never expose server CHAT_ENCRYPTION_KEY material. Client-sealed payloads are
 * returned under `e2ee` for the recipient to decrypt locally.
 *
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    private function resolveAvatarUrl(?string $avatar): ?string
    {
        if (!$avatar) {
            return null;
        }
        if (str_starts_with($avatar, 'http')) {
            return $avatar;
        }
        $disk = config('filesystems.app_media_disk');
        if (Storage::disk($disk)->exists($avatar)) {
            return Storage::disk($disk)->url($avatar);
        }

        return null;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        /** @var Message $msg */
        $msg = $this->resource;
        $sender = $msg->sender;
        $reply = $msg->replyTo;
        $isSealed = $msg->is_client_sealed;

        return [
            'uuid' => $msg->id,
            'conversation_uuid' => $msg->conversation_id,
            'sender_id' => $msg->sender_id,
            'sender' => $sender ? [
                'id' => $sender->id,
                'name' => trim("{$sender->firstname} {$sender->lastname}"),
                'avatar' => $this->resolveAvatarUrl($sender->getFirstMediaUrl('avatars') ?: $sender->avatar),
            ] : null,
            'type' => $msg->type->value,
            'is_client_sealed' => $isSealed,
            'body' => $isSealed ? null : $msg->decrypted_body,
            'e2ee' => $isSealed ? [
                'ciphertext_b64' => $msg->body,
                'iv_b64' => $msg->body_iv,
            ] : null,
            'attachments' => app(AttachmentService::class)->refreshSignedUrlsInAttachments($msg->attachments),
            'reply_to' => $reply ? [
                'uuid' => $reply->id,
                'body' => $reply->is_client_sealed
                    ? null
                    : ($reply->decrypted_body !== null
                        ? mb_substr($reply->decrypted_body, 0, 80)
                        : null),
                'sender_id' => $reply->sender_id,
                'is_client_sealed' => $reply->is_client_sealed,
            ] : null,
            'reactions' => $this->buildReactions(),
            'status' => $msg->status->value,
            'read_at' => $msg->read_at?->toIso8601String(),
            'created_at' => $msg->created_at?->toIso8601String(),
            'deleted_at' => $msg->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * Group reactions by emoji and list reacting users — frontend needs both
     * the count and the per-user mapping (to render "you reacted" state).
     *
     * @return array<int, array{emoji: string, count: int, user_ids: list<string>}>
     */
    private function buildReactions(): array
    {
        /** @var Message $msg */
        $msg = $this->resource;
        if (!$msg->relationLoaded('reactions')) {
            return [];
        }

        $grouped = $msg->reactions
            ->groupBy('emoji')
            ->map(fn ($items, $emoji) => [
                'emoji' => (string) $emoji,
                'count' => $items->count(),
                'user_ids' => $items->pluck('user_id')->map(fn ($id) => (string) $id)->values()->all(),
            ])
            ->values()
            ->all();

        return $grouped;
    }
}
