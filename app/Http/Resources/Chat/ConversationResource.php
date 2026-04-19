<?php

declare(strict_types=1);

namespace App\Http\Resources\Chat;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for a conversation item in the list or detail view.
 *
 * @mixin Conversation
 */
final class ConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Conversation $conv */
        $conv = $this->resource;

        /** @var User|null $user */
        $user  = $request->user();
        $other = $user ? $conv->otherParticipant($user) : null;
        $last  = $conv->latestMessage;

        return [
            'uuid'              => $conv->id,
            'status'            => $conv->status->value,
            'last_message_at'   => $conv->last_message_at?->toIso8601String(),
            'unread_count'      => $user ? $conv->unreadCountFor($user) : 0,
            'ad'                => $conv->ad ? [
                'id'          => $conv->ad->id,
                'title'       => $conv->ad->title,
                'cover_image' => $conv->ad->getFirstMediaUrl('images') ?: null,
            ] : null,
            'other_participant' => $other ? [
                'id'     => $other->id,
                'name'   => trim("{$other->firstname} {$other->lastname}"),
                'avatar' => $other->avatar,
            ] : null,
            'last_message'      => $last ? [
                'uuid'    => $last->id,
                'body'    => $last->decrypted_body !== null
                    ? mb_substr($last->decrypted_body, 0, 80)
                    : null,
                'type'    => $last->type->value,
                'sent_at' => $last->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}
