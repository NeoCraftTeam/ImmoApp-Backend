<?php

declare(strict_types=1);

namespace App\Http\Resources\Chat;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for a single message.
 *
 * SECURITY: Never includes body_iv, raw encrypted body, or sender phone/email.
 *
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Message $msg */
        $msg    = $this->resource;
        $sender = $msg->sender;
        $reply  = $msg->replyTo;

        return [
            'uuid'              => $msg->id,
            'conversation_uuid' => $msg->conversation_id,
            'sender_id'         => $msg->sender_id,
            'sender'            => $sender ? [
                'id'     => $sender->id,
                'name'   => trim("{$sender->firstname} {$sender->lastname}"),
                'avatar' => $sender->avatar,
            ] : null,
            'type'              => $msg->type->value,
            'body'              => $msg->decrypted_body,
            'attachments'       => $msg->attachments,
            'reply_to'          => $reply ? [
                'uuid'      => $reply->id,
                'body'      => $reply->decrypted_body !== null
                    ? mb_substr($reply->decrypted_body, 0, 80)
                    : null,
                'sender_id' => $reply->sender_id,
            ] : null,
            'status'            => $msg->status->value,
            'read_at'           => $msg->read_at?->toIso8601String(),
            'created_at'        => $msg->created_at?->toIso8601String(),
            'deleted_at'        => $msg->deleted_at?->toIso8601String(),
        ];
    }
}
