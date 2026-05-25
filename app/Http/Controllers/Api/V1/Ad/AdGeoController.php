<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ad;

use App\Http\Requests\AdRequest;
use App\Http\Resources\AdResource as AdApiResource;
use App\Models\Ad;
use App\Models\User;
use App\Support\GeoLocation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles geo-proximity queries for ads.
 *
 * CRUD operations → AdController
 * Search & facets → AdSearchController
 * Status management → AdStatusController
 */
final class AdGeoController
{
    use AuthorizesRequests;

    public function __construct(private LoggerInterface $log) {}

    /**
     * Recherche publique d'annonces à proximité par coordonnées GPS.
     *
     * @OA\Get(
     *     path="/api/v1/ads/nearby",
     *     summary="Recherche publique d'annonces à proximité par coordonnées GPS",
     *     description="Récupérer toutes les annonces dans un rayon défini autour de coordonnées GPS fournies.",
     *     operationId="obtenirAnnoncesProximitePublic",
     *     tags={"🏠 Annonces"},
     *
     *     @OA\Parameter(name="latitude", in="query", required=true, @OA\Schema(type="number", format="float", minimum=-90, maximum=90)),
     *     @OA\Parameter(name="longitude", in="query", required=true, @OA\Schema(type="number", format="float", minimum=-180, maximum=180)),
     *     @OA\Parameter(name="radius", in="query", required=false, @OA\Schema(type="number", format="float", minimum=0, default=1000)),
     *
     *     @OA\Response(response=200, description="Annonces à proximité"),
     *     @OA\Response(response=422, description="Coordonnées invalides"),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     *
     * @throws Throwable
     */
    public function ads_nearby_public(AdRequest $request): JsonResponse
    {
        return $this->ads_nearby($request, null);
    }

    /**
     * Recherche d'annonces à proximité d'un utilisateur.
     *
     * @OA\Get(
     *     path="/api/v1/ads/{user}/nearby",
     *     summary="Recherche d'annonces à proximité d'un utilisateur spécifique",
     *     description="Récupérer toutes les annonces dans un rayon défini autour de la localisation d'un utilisateur.",
     *     operationId="obtenirAnnoncesProximiteUtilisateur",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="radius", in="query", required=false, @OA\Schema(type="number", format="float", default=1000)),
     *     @OA\Parameter(name="latitude", in="query", required=false, @OA\Schema(type="number", format="float")),
     *     @OA\Parameter(name="longitude", in="query", required=false, @OA\Schema(type="number", format="float")),
     *
     *     @OA\Response(response=200, description="Annonces à proximité de l'utilisateur"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Interdit"),
     *     @OA\Response(response=404, description="Utilisateur introuvable"),
     *     @OA\Response(response=422, description="Coordonnées invalides"),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     *
     * @throws Throwable
     */
    public function ads_nearby_user(AdRequest $request, string $user): JsonResponse
    {
        return $this->ads_nearby($request, $user);
    }

    /**
     * Shared geo-proximity logic.
     *
     * @throws Throwable
     */
    private function ads_nearby(AdRequest $request, ?string $user = null): JsonResponse
    {
        $this->authorize('adsNearby', Ad::class);

        $targetUser = null;
        if ($user !== null) {
            $targetUser = User::find($user);
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $authUser = auth()->user();
            if ($authUser && $targetUser->id !== $authUser->id && !$authUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: you can only query your own location.',
                ], 403);
            }
        }

        if ($targetUser === null) {
            $targetUser = auth()->user();
        }

        $geo = GeoLocation::fromRequest($request);

        if (!$geo && $targetUser?->id) {
            /** @var object{lat: string|null, lng: string|null}|null $row */
            $row = User::query()
                ->where('id', $targetUser->id)
                ->selectRaw('ST_Y(location) as lat, ST_X(location) as lng')
                ->first();
            if ($row && is_numeric($row->lat) && is_numeric($row->lng)) {
                $geo = new GeoLocation((float) $row->lat, (float) $row->lng);
            }
        }

        if (!$geo) {
            return response()->json([
                'success' => false,
                'message' => 'Latitude and longitude are required and must be within valid ranges.',
            ], 422);
        }

        $defaultRadius = 1000;
        $radius = is_numeric($request->input('radius'))
            ? min((float) $request->input('radius'), GeoLocation::MAX_RADIUS)
            : $defaultRadius;

        $lat = $geo->latitude;
        $long = $geo->longitude;

        try {
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                $ads = Ad::query()
                    ->visible()
                    ->publiclyListed()
                    ->whereNotNull('location')
                    ->selectRaw('ad.*')
                    ->selectRaw('ST_DistanceSphere(location, ST_MakePoint(?, ?)) as distance', [$long, $lat])
                    ->selectRaw('ST_Y(location) as lat')
                    ->selectRaw('ST_X(location) as lng')
                    ->whereRaw('ST_DistanceSphere(location, ST_MakePoint(?, ?)) <= ?', [$long, $lat, $radius])
                    ->orderBy('distance', 'asc')
                    ->with(['user', 'quarter.city', 'ad_type', 'media'])
                    ->withAvg('reviews', 'rating')
                    ->withCount('reviews')
                    ->get();
            } else {
                $ads = Ad::query()
                    ->visible()
                    ->publiclyListed()
                    ->whereNotNull('location')
                    ->selectRaw('ad.*')
                    ->selectRaw('ST_Distance_Sphere(location, ST_MakePoint(?, ?)) as distance', [$long, $lat])
                    ->selectRaw('ST_Y(location) as lat')
                    ->selectRaw('ST_X(location) as lng')
                    ->whereRaw('ST_Distance_Sphere(location, ST_MakePoint(?, ?)) <= ?', [$long, $lat, $radius])
                    ->orderBy('distance', 'asc')
                    ->with(['user', 'quarter.city', 'ad_type', 'media'])
                    ->withAvg('reviews', 'rating')
                    ->withCount('reviews')
                    ->get();
            }

            $coordinates = $ads->map(fn (Ad $ad) => [
                'id' => $ad->id,
                'latitude' => isset($ad->lat) ? (float) $ad->lat : null,
                'longitude' => isset($ad->lng) ? (float) $ad->lng : null,
                'distance' => round($ad->distance ?? 0, 2),
            ])->values();

            return response()->json([
                'success' => true,
                'data' => AdApiResource::collection($ads),
                'coordinates' => $coordinates,
                'meta' => [
                    'center' => $geo->toArray(),
                    'radius' => $radius,
                    'count' => $ads->count(),
                    'user_id' => $targetUser?->id,
                ],
            ]);

        } catch (Throwable $e) {
            $this->log->error('Error in ads_nearby', [
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
