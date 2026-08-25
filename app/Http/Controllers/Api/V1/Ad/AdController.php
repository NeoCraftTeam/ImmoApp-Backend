<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ad;

use App\Actions\CreateAd;
use App\Actions\DeleteAd;
use App\Actions\UpdateAd;
use App\Enums\AdStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Requests\AdRequest;
use App\Http\Resources\AdResource as AdApiResource;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\User;
use App\Services\Ad\AdFeedService;
use App\Support\SafeApiMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles CRUD operations for ads (index, store, show, update, destroy).
 *
 * Search & facets → AdSearchController
 * Geo proximity → AdGeoController
 * Status management → AdStatusController
 */
final class AdController
{
    use AuthorizesRequests;

    public function __construct(
        private LoggerInterface $log,
        private CreateAd $createAdAction,
        private UpdateAd $updateAdAction,
        private DeleteAd $deleteAdAction,
        private AdFeedService $adFeedService,
    ) {}

    /**
     * Afficher la liste paginée des annonces.
     *
     * @OA\Get(
     *     path="/api/v1/ads",
     *     summary="Obtenir toutes les annonces",
     *     description="Récupérer une liste paginée de toutes les annonces avec leurs relations",
     *     operationId="obtenirAnnonces",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1, default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100, default=15)),
     *
     *     @OA\Response(response=200, description="Opération réussie"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Interdit")
     * )
     *
     * @throws AuthorizationException
     * @throws JsonException
     */
    public function index(AdRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('viewAny', Ad::class);

        $perPage = min(max((int) $request->integer('per_page', config('pagination.per_page', 15)), 1), 100);
        $page = max((int) $request->integer('page', 1), 1);
        $type = $request->input('type');

        $query = Ad::query()->forPublicListing();

        if ($type) {
            $query->whereHas('ad_type', fn ($q) => $q->where('name', 'ilike', "%{$type}%"));
        }

        if ($excludeIds = $request->input('exclude_ids')) {
            $ids = array_values(array_filter(array_map(strval(...), (array) $excludeIds)));
            if ($ids !== []) {
                $query->whereNotIn('id', $ids);
            }
        }

        $useCache = !$request->has('exclude_ids') && $page <= 3;

        if ($useCache) {
            $typeKey = (string) ($type ?? '');
            $cacheKey = sprintf('api:v1:ads:index:p%d:pp%d:t%s', $page, $perPage, sha1($typeKey));
            $ttl = (int) config('cache.ads_public_index_ttl', 60);

            $json = Cache::remember($cacheKey, $ttl, function () use ($query, $perPage, $page, $request): string {
                $ads = (clone $query)->orderByBoost()->paginate($perPage, ['*'], 'page', $page);

                return AdApiResource::collection($ads)->toResponse($request)->getContent();
            });

            /** @var array<string, mixed> $payload */
            $payload = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);

            return response()->json($payload);
        }

        $ads = $query->orderByBoost()->paginate($perPage, ['*'], 'page', $page);

        return AdApiResource::collection($ads);
    }

    /**
     * Cursor-paginated public feed (infinite scroll / recommendations).
     *
     * Performance: the canonical query is served by the partial index
     * `ad_feed_boost_idx (boost_score DESC, created_at DESC, id DESC)
     *   WHERE is_visible = true AND status IN ('available', 'reserved')`.
     *
     * On top of the index, the *first page* (no cursor, no exclude_ids,
     * default per_page) is hit by every guest landing on the home and is
     * shared across users — we cache the resolved Eloquent collection for
     * 300 s (5 min) so a cold load never exceeds the 1 s Nightwatch SLA.
     * Aligned with the route-level `cdn.cache:300` so Cloudflare absorbs
     * the guest traffic at the edge while the app cache backs subsequent
     * cursor pages and the rare CDN miss. Authenticated users and
     * subsequent pages bypass the cache to keep personalised
     * recommendations fresh.
     */
    public function feed(AdRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ad::class);

        $result = $this->adFeedService->build($request);

        return AdApiResource::collection($result->paginator)
            ->additional(['total_approximate' => $result->total]);
    }

    /**
     * Créer une nouvelle annonce.
     *
     * @OA\Post(
     *     path="/api/v1/ads",
     *     summary="Créer une nouvelle annonce",
     *     description="Créer une nouvelle annonce immobilière avec images et données de localisation.",
     *     operationId="creerAnnonce",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=201, description="Annonce créée avec succès"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Interdit"),
     *     @OA\Response(response=422, description="Erreurs de validation")
     * )
     *
     * @throws Throwable
     */
    public function store(AdRequest $request): JsonResponse
    {
        $this->authorize('create', Ad::class);
        $data = $request->validated();
        $userId = auth()->id();

        // ── Idempotency guard ─────────────────────────────────────────────────
        // A unique key generated once per form session prevents duplicates
        // caused by double-clicks, network retries, or accidental re-submissions.
        $idempotencyKey = $request->input('_idempotency_key');
        if ($idempotencyKey) {
            $cacheKey = "ad_create:u{$userId}:k".md5((string) $idempotencyKey);
            $lock = Cache::lock($cacheKey.':lock', 10);

            if (!$lock->get()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Requête en cours de traitement, veuillez patienter.',
                ], 409);
            }

            try {
                $existingAdId = Cache::get($cacheKey);
                if ($existingAdId) {
                    $existing = Ad::with(['media', 'user.agency', 'user.city', 'user.media', 'user.latestTrustScore', 'ad_type', 'quarter.city', 'agency'])
                        ->find($existingAdId);
                    if ($existing) {
                        $this->log->info('Idempotency hit — returning existing ad', [
                            'ad_id' => $existingAdId,
                            'user_id' => $userId,
                        ]);

                        return response()->json([
                            'success' => true,
                            'message' => 'Ad created successfully',
                            'data' => [
                                'ad' => new AdApiResource($existing),
                                'images_count' => $existing->getMedia('images')->count(),
                            ],
                        ], 201);
                    }
                }
            } catch (Throwable $e) {
                $lock->release();

                throw $e;
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        try {
            $this->log->info('Data received for ad creation:', $data);
            $this->log->info('Files received:', $request->allFiles());

            $isDraft = (bool) ($data['is_draft'] ?? false);
            unset($data['is_draft']);

            $images = $this->collectUploadedImages($request);
            $propertyConditionPdf = $request->hasFile('property_condition')
                ? $request->file('property_condition')
                : null;
            $ad = $this->createAdAction->execute($data, $images, $propertyConditionPdf, $isDraft);

            // Store idempotency key → ad ID mapping for 5 minutes
            if ($idempotencyKey) {
                Cache::put(
                    "ad_create:u{$userId}:k".md5((string) $idempotencyKey),
                    $ad->id,
                    now()->addMinutes(5)
                );
                $lock->release();
            }

            $ad->load(['media', 'user.agency', 'user.city', 'user.media', 'user.latestTrustScore', 'ad_type', 'quarter.city', 'agency']);

            return response()->json([
                'success' => true,
                'message' => $isDraft ? 'Draft saved successfully' : 'Ad created successfully',
                'data' => [
                    'ad' => new AdApiResource($ad),
                    'images_count' => $ad->getMedia('images')->count(),
                ],
            ], 201);

        } catch (Throwable $e) {
            if (isset($lock)) {
                $lock->release();
            }
            $this->log->error('Error creating ad', [
                'user_id' => $userId,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Afficher une annonce spécifique.
     *
     * @OA\Get(
     *     path="/api/v1/ads/{id}",
     *     summary="Obtenir une annonce spécifique",
     *     description="Récupérer les informations détaillées d'une annonce.",
     *     operationId="obtenirAnnonce",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Annonce récupérée"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Interdit"),
     *     @OA\Response(response=404, description="Annonce introuvable")
     * )
     */
    public function show(string $id): JsonResponse
    {
        $isUuid = (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $id
        );

        $ad = Ad::with(['media', 'user.agency', 'user.city', 'user.media', 'user.latestTrustScore', 'ad_type', 'quarter.city', 'agency', 'reviews.user'])
            ->withAvg('reviews', 'rating')
            ->withCount([
                'reviews',
                'interactions as views_count' => fn ($q) => $q->where('type', AdInteraction::TYPE_VIEW),
                'interactions as views_count_today' => fn ($q) => $q->where('type', AdInteraction::TYPE_VIEW)
                    ->where('created_at', '>=', now()->startOfDay()),
                'interactions as views_count_week' => fn ($q) => $q->where('type', AdInteraction::TYPE_VIEW)
                    ->where('created_at', '>=', now()->subDays(7)),
            ])
            ->where(function ($q) use ($id, $isUuid): void {
                if ($isUuid) {
                    $q->where('id', $id)->orWhere('slug', $id);
                } else {
                    $q->where('slug', $id);
                }
            })
            ->firstOrFail();

        $this->authorize('view', $ad);

        return response()->json([
            'success' => true,
            'data' => new AdApiResource($ad),
        ]);
    }

    /**
     * Mettre à jour une annonce existante.
     *
     * @OA\Put(
     *     path="/api/v1/ads/{id}",
     *     summary="Mettre à jour une annonce existante",
     *     description="Mettre à jour les informations d'une annonce.",
     *     operationId="mettreAJourAnnonce",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Annonce mise à jour"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Interdit"),
     *     @OA\Response(response=404, description="Annonce introuvable"),
     *     @OA\Response(response=422, description="Erreurs de validation")
     * )
     *
     * @throws Throwable
     */
    public function update(AdRequest $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);
        $data = $request->validated();
        unset($data['is_draft']);

        if (isset($data['status']) && !auth()->user()?->isAdmin()) {
            // Owners can only publish drafts (DRAFT → PENDING)
            $requestedStatus = AdStatus::tryFrom($data['status']);
            if (!($ad->status === AdStatus::DRAFT && $requestedStatus === AdStatus::PENDING)) {
                unset($data['status']);
            }
        }

        try {
            $this->log->info('Data received for ad update:', $data);
            $this->log->info('Files received:', $request->allFiles());

            $images = $this->collectUploadedImages($request);
            $imagesToDelete = is_array($request->input('images_to_delete'))
                ? $request->input('images_to_delete')
                : [];
            $propertyConditionPdf = $request->hasFile('property_condition')
                ? $request->file('property_condition')
                : null;

            $result = $this->updateAdAction->execute($ad, $data, $images, $imagesToDelete, $propertyConditionPdf);
            $ad = $result['ad'];

            $ad->load(['media', 'user.agency', 'user.city', 'user.media', 'user.latestTrustScore', 'ad_type', 'quarter.city', 'agency']);

            return response()->json([
                'success' => true,
                'message' => 'Ad updated successfully',
                'data' => [
                    'ad' => new AdApiResource($ad),
                    'images_count' => $ad->getMedia('images')->count(),
                ],
            ]);

        } catch (InvalidStatusTransitionException $e) {
            $payload = SafeApiMessage::envelope($e->getMessage(), 'INVALID_STATUS_TRANSITION', 422);

            return response()->json([
                'success' => false,
                ...$payload,
            ], 422);
        } catch (Throwable $e) {
            $this->log->error('Error updating ad', [
                'ad_id' => $ad->id,
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Supprimer définitivement une annonce.
     *
     * @OA\Delete(
     *     path="/api/v1/ads/{id}",
     *     summary="Supprimer une annonce",
     *     description="Supprimer définitivement une annonce et toutes ses images associées.",
     *     operationId="supprimerAnnonce",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Annonce supprimée"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Interdit"),
     *     @OA\Response(response=404, description="Annonce introuvable"),
     *     @OA\Response(response=422, description="Erreur lors de la suppression")
     * )
     *
     * @throws Throwable
     */
    public function destroy(string $id): JsonResponse
    {
        $ad = $this->resolveAdForAuthenticatedOwner($id);

        $this->authorize('delete', $ad);

        try {
            $imagesCount = $this->deleteAdAction->execute($ad);
        } catch (Throwable $e) {
            $this->log->error('Error deleting ad', [
                'ad_id' => $id,
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Annonce supprimée.',
            'data' => [
                'deleted_ad_id' => $id,
                'deleted_images_count' => $imagesCount,
            ],
        ]);
    }

    /**
     * Resolve an ad for owner write/delete, including drafts and soft-deleted rows
     * still visible in GET /my/ads (withTrashed).
     *
     * Non-admins only see their own ads; others receive 404 (not 403) via ModelNotFoundException.
     */
    private function resolveAdForAuthenticatedOwner(string $id): Ad
    {
        /** @var User $user */
        $user = auth()->user();

        $query = Ad::query()->withTrashed();

        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return $query->where('id', $id)->firstOrFail();
    }

    /**
     * Collect all uploaded image files from the request (supports images, image, photos field names).
     *
     * @return array<int, UploadedFile>
     */
    private function collectUploadedImages(Request $request): array
    {
        $images = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image;
            }
        }
        if ($request->hasFile('image')) {
            $images[] = $request->file('image');
        }
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $images[] = $photo;
            }
        }

        return $images;
    }
}
