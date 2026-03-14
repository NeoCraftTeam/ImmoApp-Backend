<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Ad;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConversationController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where(fn ($q) => $q->where('tenant_id', $user->id)->orWhere('landlord_id', $user->id))
            ->with(['ad:id,title,slug', 'lastMessage', 'tenant:id,firstname,lastname,avatar', 'landlord:id,firstname,lastname,avatar'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return JsonResource::collection($conversations->through(function (Conversation $conv) use ($user) {
            return [
                'id' => $conv->id,
                'ad' => $conv->ad ? ['id' => $conv->ad->id, 'title' => $conv->ad->title, 'slug' => $conv->ad->slug] : null,
                'other_party' => $user->id === $conv->tenant_id ? $conv->landlord : $conv->tenant,
                'last_message' => $conv->lastMessage ? [
                    'body' => $conv->lastMessage->body,
                    'sender_id' => $conv->lastMessage->sender_id,
                    'created_at' => $conv->lastMessage->created_at,
                ] : null,
                'unread_count' => $conv->unreadCountFor($user),
                'updated_at' => $conv->updated_at,
            ];
        }));
    }

    public function findOrCreate(Request $request, Ad $ad): JsonResponse
    {
        $user = $request->user();

        abort_if($ad->user_id === $user->id, 422, 'Vous ne pouvez pas vous envoyer un message.');

        $conversation = Conversation::firstOrCreate(
            ['ad_id' => $ad->id, 'tenant_id' => $user->id],
            ['landlord_id' => $ad->user_id]
        );

        $conversation->load(['ad:id,title,slug', 'messages.sender:id,firstname,lastname,avatar', 'tenant:id,firstname,lastname,avatar', 'landlord:id,firstname,lastname,avatar']);

        return response()->json([
            'id' => $conversation->id,
            'ad' => $conversation->ad ? ['id' => $conversation->ad->id, 'title' => $conversation->ad->title, 'slug' => $conversation->ad->slug] : null,
            'other_party' => $user->id === $conversation->tenant_id ? $conversation->landlord : $conversation->tenant,
            'messages' => $conversation->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'sender_id' => $m->sender_id,
                'sender' => $m->sender ? ['id' => $m->sender->id, 'firstname' => $m->sender->firstname, 'avatar' => $m->sender->avatar] : null,
                'created_at' => $m->created_at,
            ]),
        ]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeConversation($user, $conversation);

        $conversation->load(['ad:id,title,slug', 'messages.sender:id,firstname,lastname,avatar', 'tenant:id,firstname,lastname,avatar', 'landlord:id,firstname,lastname,avatar']);

        // Mark as read
        if ($user->id === $conversation->tenant_id) {
            $conversation->update(['tenant_last_read_at' => now()]);
        } else {
            $conversation->update(['landlord_last_read_at' => now()]);
        }

        return response()->json([
            'id' => $conversation->id,
            'ad' => $conversation->ad ? ['id' => $conversation->ad->id, 'title' => $conversation->ad->title, 'slug' => $conversation->ad->slug] : null,
            'other_party' => $user->id === $conversation->tenant_id ? $conversation->landlord : $conversation->tenant,
            'messages' => $conversation->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'sender_id' => $m->sender_id,
                'sender' => $m->sender ? ['id' => $m->sender->id, 'firstname' => $m->sender->firstname, 'avatar' => $m->sender->avatar] : null,
                'created_at' => $m->created_at,
            ]),
        ]);
    }

    private function authorizeConversation(\App\Models\User $user, Conversation $conversation): void
    {
        abort_unless(
            $conversation->tenant_id === $user->id || $conversation->landlord_id === $user->id,
            403
        );
    }
}
