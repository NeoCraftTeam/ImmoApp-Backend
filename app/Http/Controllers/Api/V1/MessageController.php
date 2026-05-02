<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Chat\ReactionRequest;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\MessageService;
use App\Services\Chat\ReactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles individual message operations: deletion, emoji reactions.
 */
final readonly class MessageController
{
    public function __construct(
        private MessageService $messages,
        private ReactionService $reactions,
    ) {}

    /**
     * DELETE /api/v1/messages/{uuid}
     * Soft-delete a message. Only the sender can delete, within 24 hours.
     */
    public function destroy(Request $request, string $uuid): Response
    {
        /** @var User $user */
        $user = $request->user();
        $message = Message::where('id', $uuid)->firstOrFail();

        // Return 404 for messages not belonging to this user's conversations (IDOR)
        $tenantId = $message->conversation?->tenant_id;
        $landlordId = $message->conversation?->landlord_id;
        abort_unless($user->id === $tenantId || $user->id === $landlordId, 404);

        $this->messages->delete($message, $user);

        return response()->noContent();
    }

    /**
     * POST /api/v1/messages/{uuid}/reactions
     * Toggle on an emoji reaction for the authenticated user.
     */
    public function addReaction(ReactionRequest $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $message = Message::where('id', $uuid)->firstOrFail();

        $this->reactions->add($message, $user, (string) $request->validated('emoji'));

        return response()->json(['ok' => true], 201);
    }

    /**
     * DELETE /api/v1/messages/{uuid}/reactions
     * Toggle off / remove an emoji reaction from the authenticated user.
     */
    public function removeReaction(ReactionRequest $request, string $uuid): Response
    {
        /** @var User $user */
        $user = $request->user();
        $message = Message::where('id', $uuid)->firstOrFail();

        $this->reactions->remove($message, $user, (string) $request->validated('emoji'));

        return response()->noContent();
    }
}
