<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CityController
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
    public function index()
    {
        $cites = City::paginate(10);

        return CityResource::collection($cites);
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
    public function store(CityRequest $request)
    {
        $this->authorize('create', City::class);
        try {

            $existingCity = City::where('name', $request->name)->first();
            if ($existingCity) {
                return response()->json([
                    'message' => 'Cette ville existe déjà',
                ], 400); // 400 = Bad Request
            }

            $city = City::create($request->validated());

            return response()->json([
                'message' => 'Ville crée avec succès',
                'data' => new CityResource($city),
            ], 201); // 201 = Created

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création',
                'error' => $e->getMessage(),
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
    public function show(string $id)
    {

        $city = City::find($id);
        if (! $city) {
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
    public function update(CityRequest $request, City $city)
    {
        $this->authorize('update', $city);

        try {
            $existingCity = City::where('name', $request->name)
                ->where('id', '!=', $city->id)
                ->first();
            if ($existingCity) {
                return response()->json([
                    'message' => 'Cette ville a déjà été modifiée',
                ], 400); // 400 = Bad Request
            }
            $city->update($request->validated());

            return response()->json([
                'message' => 'Ville mise à jour avec succès',
                'data' => new CityResource($city),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage(),
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
    public function destroy(City $city)
    {
        $this->authorize('delete', $city);

        try {
            // Vérifier s'il y a des dépendances avant suppression
            if ($city->user()->exists()) {
                return response()->json([
                    'message' => 'Impossible de supprimer cette ville car il contient des utilisateurs.',
                ], 409); // Conflict

            }
            $city->delete();

            return response()->json([
                'message' => 'Ville supprimée avec succès',
            ], 200); // 200 = OK
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la ville',
                'error' => $e->getMessage(),
            ], 500); // 500 = Internal Server Error
        }
    }
}
