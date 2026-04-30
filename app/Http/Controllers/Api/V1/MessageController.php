<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Message;
use App\Models\User;
use App\Services\Chat\MessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles individual message operations (currently: deletion).
 */
final readonly class MessageController
{
    public function __construct(private MessageService $messages) {}

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
}
