<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateAd;
use App\Actions\UpdateAd;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Requests\AdRequest;
use App\Http\Resources\AdResource as AdApiResource;
use App\Models\Ad;
use App\Models\AdInteraction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     * @return AnonymousResourceCollection Collection paginée des ressources d'annonces
     *
     * @throws AuthorizationException
     */
    public function index(AdRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ad::class);

        $perPage = min(max((int) $request->integer('per_page', config('pagination.per_page', 15)), 1), 100);
        $type = $request->input('type');

        $query = Ad::query()
            ->with('quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'agency')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->visible()
            ->publiclyListed();

        if ($type) {
            $query->whereHas('ad_type', fn ($q) => $q->where('name', 'ilike', "%{$type}%"));
        }

        if ($excludeIds = $request->input('exclude_ids')) {
            $ids = array_values(array_filter(array_map(strval(...), (array) $excludeIds)));
            if ($ids !== []) {
                $query->whereNotIn('id', $ids);
            }
        }

        $ads = $query->orderByBoost()->paginate($perPage);

        return AdApiResource::collection($ads);
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
            $existingAdId = Cache::get($cacheKey);
            if ($existingAdId) {
                $existing = Ad::with(['media', 'user.agency', 'user.city', 'ad_type', 'quarter.city', 'agency'])
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
        }
        // ─────────────────────────────────────────────────────────────────────

        try {
            $this->log->info('Data received for ad creation:', $data);
            $this->log->info('Files received:', $request->allFiles());

            $images = $this->collectUploadedImages($request);
            $propertyConditionPdf = $request->hasFile('property_condition')
                ? $request->file('property_condition')
                : null;
            $ad = $this->createAdAction->execute($data, $images, $propertyConditionPdf);

            // Store idempotency key → ad ID mapping for 5 minutes
            if ($idempotencyKey) {
                Cache::put(
                    "ad_create:u{$userId}:k".md5((string) $idempotencyKey),
                    $ad->id,
                    now()->addMinutes(5)
                );
            }

            $ad->load(['media', 'user.agency', 'user.city', 'ad_type', 'quarter.city', 'agency']);

            return response()->json([
                'success' => true,
                'message' => 'Ad created successfully',
                'data' => [
                    'ad' => new AdApiResource($ad),
                    'images_count' => $ad->getMedia('images')->count(),
                ],
            ], 201);

        } catch (Throwable $e) {
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
        $ad = Ad::with(['media', 'user.agency', 'user.city', 'ad_type', 'quarter.city', 'agency', 'reviews.user'])
            ->withAvg('reviews', 'rating')
            ->withCount([
                'reviews',
                'interactions as views_count' => fn ($q) => $q->where('type', AdInteraction::TYPE_VIEW),
                'interactions as views_count_today' => fn ($q) => $q->where('type', AdInteraction::TYPE_VIEW)
                    ->where('created_at', '>=', now()->startOfDay()),
                'interactions as views_count_week' => fn ($q) => $q->where('type', AdInteraction::TYPE_VIEW)
                    ->where('created_at', '>=', now()->subDays(7)),
            ])
            ->findOrFail($id);

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

        if (isset($data['status']) && !auth()->user()?->isAdmin()) {
            unset($data['status']);
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

            $ad->load(['media', 'user.agency', 'user.city', 'ad_type', 'quarter.city', 'agency']);

            return response()->json([
                'success' => true,
                'message' => 'Ad updated successfully',
                'data' => [
                    'ad' => new AdApiResource($ad),
                    'images_count' => $ad->getMedia('images')->count(),
                ],
            ]);

        } catch (InvalidStatusTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
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
        $ad = Ad::findOrFail($id);

        $this->authorize('delete', $ad);

        DB::beginTransaction();

        try {
            $this->log->info('Starting deletion of ad with ID: '.$id);

            $imagesCount = $ad->getMedia('images')->count();

            $ad->delete();

            $this->log->info('Ad deleted successfully with ID: '.$id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ad deleted successfully',
                'data' => [
                    'deleted_ad_id' => $id,
                    'deleted_images_count' => $imagesCount,
                ],
            ]);

        } catch (Throwable $e) {
            DB::rollBack();
            $this->log->error('Error deleting ad', [
                'ad_id' => $id,
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
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
