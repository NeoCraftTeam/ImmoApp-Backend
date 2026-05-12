<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Events\Chat\MessageReactionAdded;
use App\Events\Chat\MessageReactionRemoved;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Add / remove / list emoji reactions on chat messages.
 *
 * Authorization is performed up front: only the two participants of a
 * conversation may react to its messages. A second add of the same
 * (message, user, emoji) tuple is a no-op (idempotent).
 */
final readonly class ReactionService
{
    /**
     * Toggle / add a reaction. Returns the new reaction row, or null if the
     * caller had already added that exact emoji (idempotent add).
     */
    public function add(Message $message, User $user, string $emoji): ?MessageReaction
    {
        $this->ensureParticipant($message, $user);

        return DB::transaction(function () use ($message, $user, $emoji): ?MessageReaction {
            $existing = MessageReaction::query()
                ->where('message_id', $message->id)
                ->where('user_id', $user->id)
                ->where('emoji', $emoji)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return null;
            }

            $reaction = MessageReaction::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $emoji,
            ]);

            try {
                broadcast(new MessageReactionAdded(
                    $message->conversation_id,
                    $message->id,
                    $user->id,
                    $emoji,
                ))->toOthers();
            } catch (\Throwable) {
                // Reverb may be unavailable in local dev — do not fail the HTTP response
            }

            return $reaction;
        });
    }

    /**
     * Remove an existing reaction. Idempotent: returns true if a row was
     * removed, false if no matching reaction existed.
     */
    public function remove(Message $message, User $user, string $emoji): bool
    {
        $this->ensureParticipant($message, $user);

        $deleted = MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->delete();

        if ($deleted > 0) {
            try {
                broadcast(new MessageReactionRemoved(
                    $message->conversation_id,
                    $message->id,
                    $user->id,
                    $emoji,
                ))->toOthers();
            } catch (\Throwable) {
                // Reverb may be unavailable in local dev — do not fail the HTTP response
            }
        }

        return $deleted > 0;
    }

    /**
     * Verify the user is part of the conversation owning the message.
     * Throws 404 to avoid leaking message existence to outsiders.
     */
    private function ensureParticipant(Message $message, User $user): void
    {
        /** @var Conversation|null $conv */
        $conv = $message->conversation;
        if ($conv === null) {
            abort(404);
        }
        abort_unless(
            $user->id === $conv->tenant_id || $user->id === $conv->landlord_id,
            404,
        );
    }
}
