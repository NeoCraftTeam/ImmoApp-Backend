<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Geo;

use App\Actions\City\CreateCityAction;
use App\Actions\City\DeleteCityAction;
use App\Actions\City\ListCitiesAction;
use App\Actions\City\ShowCityAction;
use App\Actions\City\UpdateCityAction;
use App\Http\Requests\CityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class CityController
{
    use AuthorizesRequests;

    /**
     * @OA\Get(
     *     path="/api/v1/cities",
     *     operationId="showCities",
     *     security={{"bearerAuth":{}}},
     *    tags={"🏙️ Ville"},
     *     summary="Liste des villes",
     *     description="Récupère la liste paginée des villes",
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Succès",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/City")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non autorisé"),
     *     @OA\Response(response=404, description="Non trouvé"),
     *     @OA\Response(response=500, description="Erreur du Serveur")
     * )
     */
    public function index(ListCitiesAction $action)
    {
        $search = request('q');
        $perPage = min((int) request('per_page', 50), 100);

        $cacheKey = 'cities:list:'.md5(($search ?? '').':'.$perPage);
        $ttl = $search ? now()->addMinutes(5) : now()->addHour();

        $cities = Cache::remember(
            $cacheKey,
            $ttl,
            fn () => $action->handle($perPage, $search)
        );

        return CityResource::collection($cities);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cities",
     *     operationId="storeCity",
     *     security={{"bearerAuth":{}}},
     *    tags={"🏙️ Ville"},
     *     summary="Créer une ville",
     *     description="Crée une nouvelle ville",
     *
     *    @OA\RequestBody(
     *          required=true,
     *
     *          @OA\JsonContent(
     *              type="object",
     *
     *              @OA\Property(property="name", type="string", example="Paris")
     *          )
     *      ),
     *
     *     @OA\Response(
     *          response=201,
     *          description="Ville créée avec succès",
     *
     *          @OA\JsonContent(ref="#/components/schemas/City")
     *      ),
     *
     *     @OA\Response(response=400, description="Requête invalide"),
     *     @OA\Response(response=401, description="Non autorisé"),
     *     @OA\Response(response=500, description="Erreur du Serveur")
     * )
     */
    public function store(CityRequest $request, CreateCityAction $action)
    {
        $this->authorize('create', City::class);
        try {
            $city = $action->handle($request->validated());
            $this->invalidateCityCache();

            return response()->json([
                'message' => 'Ville crée avec succès',
                'data' => new CityResource($city),
            ], 201); // 201 = Created

        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
            ], 500); // 500 = Internal Server Error
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cities/{id}",
     *     operationId="showCity",
     *     security={{"bearerAuth":{}}},
     *    tags={"🏙️ Ville"},
     *     summary="Afficher une ville",
     *     description="Récupère les détails d'une ville",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Succès",
     *
     *         @OA\JsonContent(ref="#/components/schemas/City")
     *     ),
     *
     *     @OA\Response(response=404, description="Ville non trouvé"),
     *     @OA\Response(response=401, description="Non autorisé"),
     *     @OA\Response(response=500, description="Erreur du Serveur"),
     * )
     */
    public function show(string $id, ShowCityAction $action)
    {
        $city = $action->handle($id);
        if (!$city) {
            return response()->json([
                'message' => 'Ville non trouvée',
            ], 404);
        }

        return new CityResource($city);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/cities/{id}",
     *     operationId="updateCity",
     *     security={{"bearerAuth":{}}},
     *    tags={"🏙️ Ville"},
     *     summary="Mettre à jour une ville",
     *     description="Met à jour les détails d'une ville",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *    @OA\RequestBody(
     *          required=true,
     *
     *          @OA\JsonContent(
     *              type="object",
     *
     *              @OA\Property(property="name", type="string", example="Lyon")
     *          )
     *      ),
     *
     *     @OA\Response(
     *          response=200,
     *          description="Ville mise à jour avec succès",
     *
     *          @OA\JsonContent(ref="#/components/schemas/City")
     *      ),
     *
     *      @OA\Response(response=400, description="Requête invalide"),
     *      @OA\Response(response=404, description="Ville non trouvée"),
     *      @OA\Response(response=401, description="Non autorisé"),
     *      @OA\Response(response=500, description="Erreur du Serveur")
     * )
     */
    public function update(CityRequest $request, City $city, UpdateCityAction $action)
    {
        $this->authorize('update', $city);

        try {
            $city = $action->handle($city, $request->validated());
            $this->invalidateCityCache();

            return response()->json([
                'message' => 'Ville mise à jour avec succès',
                'data' => new CityResource($city),
            ], 200);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
            ], 500); // 500 = Internal Server Error
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/cities/{id}",
     *     operationId="deleteCity",
     *     security={{"bearerAuth":{}}},
     *    tags={"🏙️ Ville"},
     *     summary="Supprimer une ville",
     *     description="Supprime une ville par son ID",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Ville supprimée avec succès"
     *     ),
     *      @OA\Response(response=404, description="Ville non trouvée"),
     *      @OA\Response(response=401, description="Non autorisé"),
     *      @OA\Response(response=500, description="Erreur du Serveur")
     * )
     */
    public function destroy(City $city, DeleteCityAction $action)
    {
        $this->authorize('delete', $city);

        try {
            $action->handle($city);
            $this->invalidateCityCache();

            return response()->json([
                'message' => 'Ville supprimée avec succès',
            ], 200); // 200 = OK
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la ville',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
            ], 500); // 500 = Internal Server Error
        }
    }

    private function invalidateCityCache(): void
    {
        Cache::forget('cities:list:'.md5(':50'));
        Cache::forget('cities:list:'.md5(':100'));
    }
}
