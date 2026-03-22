<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Http\Requests\AdRequest;
use App\Http\Resources\AdResource as AdApiResource;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Support\GeoLocation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

    public function __construct(private LoggerInterface $log) {}

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
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ad::class);

        $perPage = min(max((int) request('per_page', config('pagination.per_page', 15)), 1), 100);
        $type = request('type');

        $query = Ad::query()
            ->with('quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'agency')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->visible()
            ->publiclyListed();

        if ($type) {
            $query->whereHas('ad_type', fn ($q) => $q->where('name', 'ilike', "%{$type}%"));
        }

        if ($excludeIds = request()->input('exclude_ids')) {
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

        DB::beginTransaction();

        try {
            $this->log->info('Data received for ad creation:', $data);
            $this->log->info('Files received:', $request->allFiles());

            $ad = new Ad;
            $ad->fill([
                'title' => $data['title'],
                'description' => $data['description'],
                'adresse' => $data['adresse'],
                'price' => $data['price'],
                'surface_area' => $data['surface_area'],
                'bedrooms' => $data['bedrooms'],
                'bathrooms' => $data['bathrooms'],
                'has_parking' => $data['has_parking'] ?? false,
                'location' => GeoLocation::fromArray($data)?->toPoint(),
                'expires_at' => $data['expires_at'] ?? null,
                'user_id' => auth()->id(),
                'quarter_id' => $data['quarter_id'],
                'type_id' => $data['type_id'],
                'attributes' => $data['attributes'] ?? [],
            ]);
            $ad->forceFill(['status' => AdStatus::PENDING]);
            $ad->save();

            $this->log->info('Ad created with ID: '.$ad->id);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $ad->addMedia($image)
                        ->toMediaCollection('images');
                }
            }
            if ($request->hasFile('image')) {
                $ad->addMediaFromRequest('image')->toMediaCollection('images');
            }
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $ad->addMedia($photo)->toMediaCollection('images');
                }
            }

            DB::commit();

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
            DB::rollback();

            $this->log->error('Error creating ad: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating ad',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred.',
            ], 422);
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

        try {
            DB::beginTransaction();

            $this->log->info('Data received for ad update:', $data);
            $this->log->info('Files received:', $request->allFiles());

            $geo = GeoLocation::fromArray($data);
            if ($geo) {
                $data['location'] = $geo->toPoint();
            }

            if (isset($data['status']) && !auth()->user()?->isAdmin()) {
                unset($data['status']);
            }

            $newStatus = null;
            if (isset($data['status'])) {
                $newStatus = AdStatus::from($data['status']);
                if ($ad->status !== $newStatus) {
                    if (!$ad->status->canTransitionTo($newStatus)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Transition de statut invalide : {$ad->status->getLabel()} → {$newStatus->getLabel()}.",
                        ], 422);
                    }
                }
                unset($data['status']);
            }

            $ad->update($data);

            if ($newStatus !== null) {
                $ad->forceFill(['status' => $newStatus])->save();
            }

            $this->log->info('Ad updated with ID: '.$ad->id);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $ad->addMedia($image)->toMediaCollection('images');
                }
            }
            if ($request->hasFile('image')) {
                $ad->addMediaFromRequest('image')->toMediaCollection('images');
            }
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $ad->addMedia($photo)->toMediaCollection('images');
                }
            }

            if ($request->has('images_to_delete') && is_array($request->input('images_to_delete'))) {
                foreach ($request->input('images_to_delete') as $mediaId) {
                    $media = $ad->media()->find($mediaId);
                    if ($media) {
                        $media->delete();
                    }
                }
            }

            $this->log->info('Media updated for ad ID: '.$ad->id);

            DB::commit();

            $ad->load([
                'media',
                'user.agency',
                'user.city',
                'ad_type',
                'quarter.city',
                'agency',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ad updated successfully',
                'data' => [
                    'ad' => new AdApiResource($ad),
                    'images_count' => $ad->getMedia('images')->count(),
                ],
            ]);

        } catch (Throwable $e) {
            DB::rollback();

            $this->log->error('Error updating ad: '.$e->getMessage(), [
                'ad_id' => $ad->id,
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating ad',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while updating the ad.',
            ], 422);
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

            $this->log->error('Error deleting ad: '.$e->getMessage(), [
                'ad_id' => $id,
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting ad',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while deleting the ad.',
            ], 422);
        }
    }
}
