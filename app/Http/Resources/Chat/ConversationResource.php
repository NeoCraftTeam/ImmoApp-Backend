<?php

declare(strict_types=1);

namespace App\Http\Resources\Chat;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * API resource for a conversation item in the list or detail view.
 *
 * @mixin Conversation
 */
final class ConversationResource extends JsonResource
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
        /** @var Conversation $conv */
        $conv = $this->resource;

        /** @var User|null $user */
        $user = $request->user();
        $other = $user ? $conv->otherParticipant($user) : null;
        $last = $conv->latestMessage;

        return [
            'uuid' => $conv->id,
            'status' => $conv->status->value,
            'last_message_at' => $conv->last_message_at?->toIso8601String(),
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
                'avatar' => $this->resolveAvatarUrl($other->getFirstMediaUrl('avatars') ?: $other->avatar),
                // ISO-8601 timestamp of last authenticated activity. Frontend
                // pairs this with the Pusher presence channel to render
                // "Vu il y a X" when the user is offline.
                'last_seen_at' => $other->last_seen_at?->toIso8601String(),
            ] : null,
            'last_message' => $last ? [
                'uuid' => $last->id,
                'sender_id' => $last->sender_id,
                'body' => $last->decrypted_body !== null
                    ? mb_substr($last->decrypted_body, 0, 80)
                    : null,
                'type' => $last->type->value,
                'sent_at' => $last->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}
