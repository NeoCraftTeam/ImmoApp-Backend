<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MessageController
{
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->tenant_id === $user->id || $conversation->landlord_id === $user->id,
            403
        );

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'is_whatsapp_forwarded' => ['boolean'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $data['body'],
            'is_whatsapp_forwarded' => $data['is_whatsapp_forwarded'] ?? false,
        ]);

        $conversation->touch();

        // Mark conversation as read for sender
        if ($user->id === $conversation->tenant_id) {
            $conversation->update(['tenant_last_read_at' => now()]);
        } else {
            $conversation->update(['landlord_last_read_at' => now()]);
        }

        $message->load('sender:id,firstname,lastname,avatar');

        return response()->json([
            'id' => $message->id,
            'body' => $message->body,
            'sender_id' => $message->sender_id,
            'sender' => $message->sender ? ['id' => $message->sender->id, 'firstname' => $message->sender->firstname, 'avatar' => $message->sender->avatar] : null,
            'created_at' => $message->created_at,
        ], 201);
    }
}
