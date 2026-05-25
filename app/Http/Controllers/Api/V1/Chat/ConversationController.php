<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Enums\ConversationStatus;
use App\Events\Chat\UserTyping;
use App\Exceptions\Chat\ConversationNotAllowedException;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Chat\SetTypingRequest;
use App\Http\Requests\Chat\StoreConversationRequest;
use App\Http\Requests\Chat\UploadAttachmentRequest;
use App\Http\Resources\Chat\ConversationResource;
use App\Http\Resources\Chat\MessageResource;
use App\Jobs\MarkConversationReadJob;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\AttachmentService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageService;
use App\Support\ApiResponse;
use App\Support\ChatE2eeSchema;
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
 *
 * @OA\Tag(name="💬 Messagerie", description="Chat locataire-bailleur : conversations, messages, pièces jointes, E2EE")
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
     *
     * @OA\Get(
     *     path="/api/v1/conversations",
     *     summary="Lister mes conversations",
     *     description="Retourne la liste paginée des conversations de l'utilisateur connecté (en tant que locataire ou bailleur). Triées par dernier message.",
     *     operationId="listConversations",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Liste des conversations"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
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
     *
     * @OA\Post(
     *     path="/api/v1/conversations",
     *     summary="Créer ou retrouver une conversation",
     *     description="Crée une nouvelle conversation locataire-bailleur sur une annonce, ou retourne l'existante. Le locataire doit avoir débloqué l'annonce. Retourne 201 si nouvelle, 200 si existante.",
     *     operationId="storeConversation",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"ad_id"},
     *
     *         @OA\Property(property="ad_id", type="string", format="uuid", description="UUID de l'annonce concernée")
     *     )),
     *
     *     @OA\Response(response=200, description="Conversation existante retrouvée"),
     *     @OA\Response(response=201, description="Nouvelle conversation créée"),
     *     @OA\Response(response=403, description="Accès non autorisé (annonce non débloquée)"),
     *     @OA\Response(response=404, description="Annonce introuvable")
     * )
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
            $conv->load([
                'ad:id,title,slug',
                ChatE2eeSchema::userParticipantEagerLoadSpec('tenant'),
                ChatE2eeSchema::userParticipantEagerLoadSpec('landlord'),
            ]);
            $this->conversations->attachPreviewMessage($conv);

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
     *
     * @OA\Get(
     *     path="/api/v1/conversations/{uuid}",
     *     summary="Détail d'une conversation",
     *     description="Retourne le détail d'une conversation avec les profils des participants. Retourne 404 si l'utilisateur n'est pas participant (prévention IDOR).",
     *     operationId="showConversation",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, description="UUID de la conversation", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Détail de la conversation"),
     *     @OA\Response(response=404, description="Conversation introuvable ou accès non autorisé")
     * )
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $conv = $this->findConversationForUser($uuid, (string) $request->user()?->id);
        $conv->load([
            'ad:id,title,slug',
            ChatE2eeSchema::userParticipantEagerLoadSpec('tenant'),
            ChatE2eeSchema::userParticipantEagerLoadSpec('landlord'),
        ]);
        $this->conversations->attachPreviewMessage($conv);

        return new ConversationResource($conv)->response();
    }

    /**
     * GET /api/v1/conversations/{uuid}/messages
     * Return cursor-paginated message history (newest first).
     * Automatically marks messages as read.
     *
     * @OA\Get(
     *     path="/api/v1/conversations/{uuid}/messages",
     *     summary="Historique des messages",
     *     description="Retourne les messages d'une conversation en pagination par curseur (newest first). Marque automatiquement les messages comme lus à la première page.",
     *     operationId="conversationMessages",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="cursor", in="query", description="Curseur pour la pagination (retourné dans next_cursor)", @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Messages avec curseur de pagination", @OA\JsonContent(
     *
     *         @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="next_cursor", type="string", nullable=true),
     *         @OA\Property(property="has_more", type="boolean")
     *     )),
     *
     *     @OA\Response(response=404, description="Conversation introuvable ou non-participant")
     * )
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

        // Mark-as-read only for the first page (no cursor). Pagination requests
        // must not enqueue duplicate jobs, bulk UPDATEs, and broadcasts.
        if (!filled($request->query('cursor'))) {
            MarkConversationReadJob::dispatch($conv->id, $user->id);
        }

        return response()->json([
            'data' => MessageResource::collection($paginator->items()),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    /**
     * POST /api/v1/conversations/{uuid}/messages
     * Send a message in the conversation.
     *
     * @OA\Post(
     *     path="/api/v1/conversations/{uuid}/messages",
     *     summary="Envoyer un message",
     *     description="Envoie un message texte ou chiffré E2EE dans une conversation. Supporte les types text, image, file et les pièces jointes pré-uploadées.",
     *     operationId="sendMessage",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="body", type="string", nullable=true, description="Corps du message (vide pour les messages chiffrés)"),
     *         @OA\Property(property="type", type="string", enum={"text","image","file"}, default="text"),
     *         @OA\Property(property="reply_to_id", type="string", format="uuid", nullable=true),
     *         @OA\Property(property="attachments", type="array", nullable=true, @OA\Items(type="object")),
     *         @OA\Property(property="is_client_sealed", type="boolean", default=false, description="true si le message est chiffré côté client (E2EE)"),
     *         @OA\Property(property="e2ee_ciphertext_b64", type="string", nullable=true),
     *         @OA\Property(property="e2ee_iv_b64", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=201, description="Message envoyé"),
     *     @OA\Response(response=404, description="Conversation introuvable ou non-participant"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function sendMessage(SendMessageRequest $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conv = $this->findConversationForUser($uuid, $user->id);

        $validated = $request->validated();

        $e2ee = null;
        if ($request->boolean('is_client_sealed')) {
            $e2ee = [
                'ciphertext_b64' => (string) $validated['e2ee_ciphertext_b64'],
                'iv_b64' => (string) $validated['e2ee_iv_b64'],
                'wrapped_keys' => $validated['e2ee_wrapped_keys'] ?? null,
            ];
        }

        $message = $this->messages->send(
            $conv,
            $user,
            (string) ($validated['body'] ?? ''),
            (string) ($validated['type'] ?? 'text'),
            $validated['attachments'] ?? null,
            $validated['reply_to_id'] ?? null,
            $e2ee,
        );

        return new MessageResource($message)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/v1/conversations/{uuid}/attachments
     * Upload a file attachment. Returns a descriptor with a signed URL.
     * Client calls this first, then sends the message with the attachment data.
     *
     * @OA\Post(
     *     path="/api/v1/conversations/{uuid}/attachments",
     *     summary="Téléverser une pièce jointe",
     *     description="Upload un fichier dans la conversation. Retourne un descripteur avec URL signée. À appeler avant `sendMessage` pour inclure la pièce jointe.",
     *     operationId="uploadConversationAttachment",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *
     *         @OA\Schema(required={"file"},
     *
     *             @OA\Property(property="file", type="string", format="binary", description="Fichier (image, PDF, etc., max 20 Mo)")
     *         )
     *     )),
     *
     *     @OA\Response(response=201, description="Descripteur de pièce jointe avec URL signée"),
     *     @OA\Response(response=422, description="Type ou taille invalide"),
     *     @OA\Response(response=404, description="Conversation introuvable")
     * )
     */
    public function uploadAttachment(UploadAttachmentRequest $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conv = $this->findConversationForUser($uuid, $user->id);

        abort_if(
            $conv->status === ConversationStatus::Archived,
            422,
            'Cannot upload attachments to an archived conversation.',
        );

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
     *
     * @OA\Patch(
     *     path="/api/v1/conversations/{uuid}/read",
     *     summary="Marquer une conversation comme lue",
     *     operationId="markConversationAsRead",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Timestamps de lecture mis à jour", @OA\JsonContent(
     *
     *         @OA\Property(property="tenant_last_read_at", type="string", format="date-time", nullable=true),
     *         @OA\Property(property="landlord_last_read_at", type="string", format="date-time", nullable=true)
     *     )),
     *
     *     @OA\Response(response=404, description="Conversation introuvable")
     * )
     */
    public function markAsRead(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conv = $this->findConversationForUser($uuid, $user->id);

        $this->conversations->markAsRead($conv, $user);

        // Single refresh — ConversationService::markAsRead() updated the row,
        // but we need the new timestamp for the API response.
        $fresh = $conv->fresh();

        return response()->json([
            'tenant_last_read_at' => $fresh?->tenant_last_read_at?->toIso8601String(),
            'landlord_last_read_at' => $fresh?->landlord_last_read_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/conversations/{uuid}/typing
     * Broadcast a typing indicator. No DB write — ephemeral.
     * Returns 204 No Content.
     *
     * @OA\Post(
     *     path="/api/v1/conversations/{uuid}/typing",
     *     summary="Indicateur de frappe (typing indicator)",
     *     description="Diffuse un événement WebSocket éphémère indiquant que l'utilisateur est en train d'écrire. Aucune écriture en base.",
     *     operationId="setTyping",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"is_typing"},
     *
     *         @OA\Property(property="is_typing", type="boolean")
     *     )),
     *
     *     @OA\Response(response=204, description="Événement diffusé (pas de contenu)")
     * )
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
     *
     * @OA\Patch(
     *     path="/api/v1/conversations/{uuid}/archive",
     *     summary="Archiver une conversation",
     *     operationId="archiveConversation",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Conversation archivée", @OA\JsonContent(
     *
     *         @OA\Property(property="status", type="string", example="archived")
     *     )),
     *
     *     @OA\Response(response=404, description="Conversation introuvable")
     * )
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
     * PATCH /api/v1/conversations/{uuid}/unarchive
     * Restore an archived conversation. Only participants.
     *
     * @OA\Patch(
     *     path="/api/v1/conversations/{uuid}/unarchive",
     *     summary="Désarchiver une conversation",
     *     operationId="unarchiveConversation",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Conversation désarchivée"),
     *     @OA\Response(response=404, description="Conversation introuvable")
     * )
     */
    public function unarchive(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $conv = $this->findConversationForUser($uuid, $user->id);

        $this->conversations->unarchive($conv, $user);
        $conv->refresh();
        $conv->load([
            'ad:id,title,slug',
            ChatE2eeSchema::userParticipantEagerLoadSpec('tenant'),
            ChatE2eeSchema::userParticipantEagerLoadSpec('landlord'),
        ]);
        $this->conversations->attachPreviewMessage($conv);

        return new ConversationResource($conv)->response();
    }

    /**
     * GET /api/v1/conversations/unread-count
     * Return total unread count and per-conversation breakdown (Redis-cached 30s).
     *
     * @OA\Get(
     *     path="/api/v1/conversations/unread-count",
     *     summary="Nombre de messages non lus",
     *     description="Retourne le total de messages non lus et le détail par conversation. Résultat mis en cache Redis 30 secondes.",
     *     operationId="conversationsUnreadCount",
     *     tags={"💬 Messagerie"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Compteurs non lus", @OA\JsonContent(
     *
     *         @OA\Property(property="total", type="integer", example=5),
     *         @OA\Property(property="by_conversation", type="object")
     *     ))
     * )
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
