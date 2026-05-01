<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Asynchronously mark a conversation as read for a user.
 *
 * Extracted from the synchronous GET /conversations/{uuid}/messages handler
 * to keep the request fast: the GET previously blocked on a transaction +
 * bulk UPDATE on messages + broadcast (often >1 s when many unread messages).
 *
 * The job runs on the `chat` queue so reads are processed near-real-time
 * without blocking the HTTP response.
 */
final class MarkConversationReadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(
        public string $conversationId,
        public string $userId,
    ) {
        $this->onQueue('chat');
    }

    public function handle(ConversationService $conversations): void
    {
        $conv = Conversation::find($this->conversationId);
        $user = User::find($this->userId);

        if ($conv === null || $user === null) {
            return;
        }

        if ($user->id !== $conv->tenant_id && $user->id !== $conv->landlord_id) {
            return;
        }

        $conversations->markAsRead($conv, $user);
    }
}
