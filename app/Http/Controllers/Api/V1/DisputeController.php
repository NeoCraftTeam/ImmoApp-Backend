<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DisputeEvidenceType;
use App\Enums\UserRole;
use App\Http\Requests\StoreDisputeMessageRequest;
use App\Http\Requests\StoreDisputeRequest;
use App\Http\Requests\TransitionDisputeRequest;
use App\Http\Requests\UploadDisputeEvidenceRequest;
use App\Http\Resources\DisputeEvidenceResource;
use App\Http\Resources\DisputeMessageResource;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Services\DisputeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class DisputeController
{
    use AuthorizesRequests;

    public function __construct(
        private DisputeService $disputes,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/disputes",
     *     summary="Lister les litiges",
     *     description="Retourne les litiges de l'utilisateur connecté. Les admins voient tous les litiges. Filtrable par `status` et `open_only`.",
     *     operationId="listDisputes",
     *     tags={"⚖️ Litiges"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="status", in="query", description="Filtrer par statut (open, in_review, resolved, closed)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="open_only", in="query", description="Si true, retourne uniquement les litiges ouverts", @OA\Schema(type="boolean")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée de litiges",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Dispute::class);

        $user = $request->user();
        $isAdmin = ($user->role ?? null) === UserRole::ADMIN;

        $query = Dispute::query()
            ->with(['initiator', 'respondent', 'ad'])
            ->latest();

        if (!$isAdmin) {
            $query->involving($user->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', is_string($status) ? $status : '');
        }

        if ($request->boolean('open_only')) {
            $query->open();
        }

        return DisputeResource::collection($query->paginate(20));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/disputes",
     *     summary="Ouvrir un litige",
     *     description="Crée un nouveau litige entre deux parties sur une annonce.",
     *     operationId="openDispute",
     *     tags={"⚖️ Litiges"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"ad_id","respondent_id","reason"},
     *
     *             @OA\Property(property="ad_id", type="string", format="uuid", description="UUID de l'annonce concernée"),
     *             @OA\Property(property="respondent_id", type="string", format="uuid", description="UUID de l'utilisateur mis en cause"),
     *             @OA\Property(property="reason", type="string", description="Motif du litige"),
     *             @OA\Property(property="description", type="string", description="Description détaillée")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Litige ouvert avec succès"),
     *     @OA\Response(response=422, description="Données invalides"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function store(StoreDisputeRequest $request): JsonResponse
    {
        $dispute = $this->disputes->open($request->user(), $request->validated());

        return DisputeResource::make($dispute->loadMissing(['initiator', 'respondent', 'ad']))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/disputes/{dispute}",
     *     summary="Détail d'un litige",
     *     description="Retourne le litige avec ses messages, preuves et parties impliquées.",
     *     operationId="showDispute",
     *     tags={"⚖️ Litiges"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="dispute", in="path", required=true, description="UUID du litige", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Litige avec messages et preuves"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=404, description="Litige introuvable")
     * )
     */
    public function show(Request $request, Dispute $dispute): DisputeResource
    {
        $this->authorize('view', $dispute);

        $dispute->load(['initiator', 'respondent', 'admin', 'ad', 'messages.sender', 'evidences']);

        return DisputeResource::make($dispute);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/disputes/{dispute}/messages",
     *     summary="Ajouter un message à un litige",
     *     description="Envoie un message dans le fil de discussion du litige. Les admins peuvent envoyer des messages internes.",
     *     operationId="storeDisputeMessage",
     *     tags={"⚖️ Litiges"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="dispute", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"body"},
     *
     *             @OA\Property(property="body", type="string", description="Contenu du message"),
     *             @OA\Property(property="is_internal", type="boolean", description="Message interne admin uniquement")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Message envoyé"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=404, description="Litige introuvable")
     * )
     */
    public function storeMessage(StoreDisputeMessageRequest $request, Dispute $dispute): JsonResponse
    {
        $this->authorize('reply', $dispute);

        $message = $this->disputes->addMessage(
            $dispute,
            $request->user(),
            $request->string('body')->toString(),
            $request->boolean('is_internal'),
        );

        return DisputeMessageResource::make($message->loadMissing('sender'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/disputes/{dispute}/evidence",
     *     summary="Téléverser une preuve",
     *     description="Ajoute un fichier (image, PDF) comme preuve dans un litige.",
     *     operationId="uploadDisputeEvidence",
     *     tags={"⚖️ Litiges"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="dispute", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"file","type"},
     *
     *                 @OA\Property(property="file", type="string", format="binary", description="Fichier preuve (image ou PDF, max 10 Mo)"),
     *                 @OA\Property(property="type", type="string", description="Type de preuve (photo, document, receipt, other)")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Preuve ajoutée"),
     *     @OA\Response(response=422, description="Fichier invalide"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function uploadEvidence(UploadDisputeEvidenceRequest $request, Dispute $dispute): JsonResponse
    {
        $this->authorize('uploadEvidence', $dispute);

        $evidence = $this->disputes->uploadEvidence(
            $dispute,
            $request->user(),
            $request->file('file'),
            DisputeEvidenceType::from($request->string('type')->toString()),
        );

        return DisputeEvidenceResource::make($evidence)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/disputes/{dispute}/transition",
     *     summary="Changer le statut d'un litige",
     *     description="Transition d'état d'un litige (admin uniquement pour plupart des transitions). Statuts possibles : in_review, resolved, closed.",
     *     operationId="transitionDispute",
     *     tags={"⚖️ Litiges"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="dispute", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"status"},
     *
     *             @OA\Property(property="status", type="string", enum={"in_review","resolved","closed"}, description="Nouveau statut cible"),
     *             @OA\Property(property="resolution_note", type="string", description="Note de résolution (recommandée pour resolved/closed)")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Statut mis à jour"),
     *     @OA\Response(response=422, description="Transition invalide"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function transition(TransitionDisputeRequest $request, Dispute $dispute): DisputeResource
    {
        $this->authorize('transition', $dispute);

        $target = $request->targetStatus();

        $dispute = $this->disputes->transition(
            $dispute,
            $request->user(),
            $target,
            $request->input('resolution_note'),
        );

        return DisputeResource::make($dispute->loadMissing(['initiator', 'respondent', 'admin']));
    }
}
