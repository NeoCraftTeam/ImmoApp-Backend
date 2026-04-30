<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\Chat\UserTyping;
use App\Exceptions\Chat\ConversationNotAllowedException;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Chat\SetTypingRequest;
use App\Http\Requests\Chat\StoreConversationRequest;
use App\Http\Requests\Chat\UploadAttachmentRequest;
use App\Http\Resources\Chat\ConversationResource;
use App\Http\Resources\Chat\MessageResource;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\AttachmentService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

/**
 * Chat conversation and message endpoints.
 *
 * SECURITY:
 *  - All methods return 404 (not 403) for conversations belonging to other users (IDOR prevention).
 *  - Encrypted body, body_iv, and sender PII are never returned.
 *  - Rate limiting applied per action (see routes/api.php).
 */
final readonly class ConversationController
{
    public function __construct(
        private ConversationService $conversations,
        private MessageService $messages,
        private AttachmentService $attachments,
    ) {}

    /**
     * GET /api/v1/conversations
     * Return paginated conversation list for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $perPage = (int) config('chat.pagination.conversations', 20);
        $paginator = $this->conversations->getConversationsForUser($user, $perPage);

        return ConversationResource::collection($paginator);
    }

    /**
     * POST /api/v1/conversations
     * Find or create a conversation after the tenant has unlocked an ad.
     * Returns 200 for existing conversations, 201 for new ones.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $adId = (string) $request->validated('ad_id');
        $ad = Ad::findOrFail($adId);
        $landlordId = (string) $ad->user_id;

        try {
            $existed = Conversation::where('ad_id', $ad->id)
                ->where('tenant_id', $user->id)
                ->exists();

            $conv = $this->conversations->findOrCreate($ad->id, $user->id, $landlordId);
            $conv->load(['ad:id,title,slug', 'latestMessage', 'tenant:id,firstname,lastname,avatar,last_seen_at', 'landlord:id,firstname,lastname,avatar,last_seen_at']);

            $status = $existed ? 200 : 201;

            return new ConversationResource($conv)
                ->response()
                ->setStatusCode($status);
        } catch (ConversationNotAllowedException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }
    }

    /**
     * GET /api/v1/conversations/{uuid}
     * Return a single conversation detail (404 for non-participants).
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $conv = $this->findConversationForUser($uuid, (string) $request->user()?->id);
        $conv->load(['ad:id,title,slug', 'latestMessage', 'tenant:id,firstname,lastname,avatar,last_seen_at', 'landlord:id,firstname,lastname,avatar,last_seen_at']);

        return new ConversationResource($conv)->response();
    }

    /**
     * GET /api/v1/conversations/{uuid}/messages
     * Return cursor-paginated message history (newest first).
     * Automatically marks messages as read.
     */
    public function messages(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conv = $this->findConversationForUser($uuid, $user->id);

        $paginator = $this->messages->getHistory(
            $conv,
            $request->query('cursor'),
            (int) config('chat.pagination.messages', 30),
        );

        $this->conversations->markAsRead($conv, $user);

        return response()->json([
            'data' => MessageResource::collection($paginator->items()),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * POST /api/v1/conversations/{uuid}/messages
     * Send a message in the conversation.
     */
    public function sendMessage(SendMessageRequest $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conv = $this->findConversationForUser($uuid, $user->id);

        $validated = $request->validated();

        $message = $this->messages->send(
            $conv,
            $user,
            (string) ($validated['body'] ?? ''),
            (string) ($validated['type'] ?? 'text'),
            $validated['attachments'] ?? null,
            $validated['reply_to_id'] ?? null,
        );

        return new MessageResource($message)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/v1/conversations/{uuid}/attachments
     * Upload a file attachment. Returns a descriptor with a signed URL.
     * Client calls this first, then sends the message with the attachment data.
     */
    public function uploadAttachment(UploadAttachmentRequest $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conv = $this->findConversationForUser($uuid, $user->id);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $descriptor = $this->attachments->upload($file, $conv);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return response()->json(['data' => $descriptor], 201);
    }

    /**
     * PATCH /api/v1/conversations/{uuid}/read
     * Mark all messages in the conversation as read for the authenticated user.
     */
    public function markAsRead(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conv = $this->findConversationForUser($uuid, $user->id);

        $this->conversations->markAsRead($conv, $user);

        return response()->json([
            'tenant_last_read_at' => $conv->fresh()?->tenant_last_read_at?->toIso8601String(),
            'landlord_last_read_at' => $conv->fresh()?->landlord_last_read_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/conversations/{uuid}/typing
     * Broadcast a typing indicator. No DB write — ephemeral.
     * Returns 204 No Content.
     */
    public function setTyping(SetTypingRequest $request, string $uuid): Response
    {
        /** @var User $user */
        $user = $request->user();
        $conv = $this->findConversationForUser($uuid, $user->id);

        try {
            broadcast(new UserTyping(
                $conv->id,
                $user->id,
                (bool) $request->validated('is_typing'),
            ))->toOthers();
        } catch (\Throwable) {
            // Reverb may be unavailable in local dev — do not fail the HTTP response
        }

        return response()->noContent();
    }

    /**
     * PATCH /api/v1/conversations/{uuid}/archive
     * Archive a conversation. Only the authenticated participant may archive.
     */
    public function archive(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conv = $this->findConversationForUser($uuid, $user->id);

        $this->conversations->archive($conv, $user);

        return response()->json(['status' => 'archived']);
    }

    /**
     * GET /api/v1/conversations/unread-count
     * Return total unread count and per-conversation breakdown (Redis-cached 30s).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->conversations->getUnreadCount($user));
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * Resolve a conversation and verify the given user is a participant.
     * Aborts with 404 (not 403) to prevent IDOR enumeration.
     */
    private function findConversationForUser(string $uuid, string $userId): Conversation
    {
        $conv = Conversation::where('id', $uuid)->firstOrFail();

        abort_unless($userId === $conv->tenant_id || $userId === $conv->landlord_id, 404);

        return $conv;
    }
}
