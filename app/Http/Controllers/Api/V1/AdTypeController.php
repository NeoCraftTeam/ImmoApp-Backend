<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AdTypeRequest;
use App\Http\Resources\AdTypeResource;
use App\Models\AdType;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AdTypeController
{
    use AuthorizesRequests;

    /**
     * @OA\Get(
     *     path="/api/v1/ad-types",
     *     operationId="showAdTypes",
     *     security={{"bearerAuth":{}}},
     *     tags={"🏷️ Type d'annonce"},
     *     summary="Types d'annonces",
     *     description="Récupère la liste des types d'annonces",
     *     @OA\Response(
     *         response=200,
     *         description="Succès",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/AdType")
     *         ),
     *     ),
     *     @OA\Response(response=401, description="Non autorisé"),
     *     @OA\Response(response=404, description="Non trouvé"),
     *     @OA\Response(response=500, description="Erreur du Serveur")
     * )
     */
    public function index()
    {
        $this->authorize('viewAny', AdType::class);
        $adTypes = AdType::all();

        return AdTypeResource::collection($adTypes);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/ad-types",
     *     operationId="storeAdType",
     *     security={{"bearerAuth":{}}},
     *     tags={"🏷️ Type d'annonce"},
     *     summary="Creer un type d'annonce",
     *    @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="name", type="string", example="Chambre"),
     *              @OA\Property(property="desc", type="string", example="C'est une chambre")
     *          )
     *      ),
     *     @OA\Response(
     *          response=201,
     *          description="Crée avec succès",
     *      ),
     *     @OA\Response(response=400, description="Requête invalide"),
     *     @OA\Response(response=401, description="Non autorisé"),
     *     @OA\Response(response=500, description="Erreur du Serveur")
     * )
     */
    public function store(AdTypeRequest $request)
    {
        $this->authorize('create', AdType::class);

        try {

            $existingAdType = AdType::where('name', $request->name)->first();
            if ($existingAdType) {
                return response()->json([
                    'message' => 'Ce type existe déjà',
                ], 400); // 400 = Bad Request
            }

            $type = AdType::create($request->validated());
            return response()->json([
                'message' => 'Crée avec succès',
                'data' => new adTypeResource($type),
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
     *     path="/api/v1/ad-types/{id}",
     *     operationId="showAdType",
     *     security={{"bearerAuth":{}}},
     *     tags={"🏷️ Type d'annonce"},
     *     summary="Afficher un type d'annonce",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Succès",
     *         @OA\JsonContent(ref="#/components/schemas/AdType")
     *     ),
     *     @OA\Response(response=404, description="Type non trouvé"),
     *     @OA\Response(response=401, description="Non autorisé"),
     *     @OA\Response(response=500, description="Erreur du Serveur"),
     * )
     */
    public function show(AdType $adType)
    {
        $this->authorize('view', $adType);

        if (!$adType) {
            return response()->json([
                'message' => 'Type non trouvé',
            ], 404);
        }
        return new AdTypeResource($adType);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/ad-types/{id}",
     *     operationId="updateAdType",
     *     security={{"bearerAuth":{}}},
     *     tags={"🏷️ Type d'annonce"},
     *     summary="Mettre à jour du type d'annonce",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *    @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="name", type="string", example="Chambre")
     *          )
     *      ),
     *     @OA\Response(
     *          response=200,
     *          description="Mise à jour avec succès",
     *          @OA\JsonContent(ref="#/components/schemas/AdType")
     *      ),
     *      @OA\Response(response=400, description="Requête invalide"),
     *      @OA\Response(response=404, description="Type non trouvée"),
     *      @OA\Response(response=401, description="Non autorisé"),
     *      @OA\Response(response=500, description="Erreur du Serveur")
     * )
     */
    public function update(AdTypeRequest $request, AdType $adType)
    {
        $this->authorize('update', $adType);

        try {
            $existingType = AdType::where('name', $request->name)
                ->where('desc', $request->desc)
                ->where('id', '!=', $adType->id)
                ->first();
            if ($existingType) {
                return response()->json([
                    'message' => 'Ce type a déjà été modifiée',
                ], 400); // 400 = Bad Request
            }
            $adType->update($request->validated());

            return response()->json([
                'message' => 'Mise à jour avec succès',
                'data' => new AdTypeResource($adType),
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
     *     path="/api/v1/ad-types/{id}",
     *     operationId="deleteAdType",
     *     security={{"bearerAuth":{}}},
     *     tags={"🏷️ Type d'annonce"},
     *     summary="Supprimer un type",
     *     description="Supprime le type par son ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Supprimée avec succès"
     *     ),
     *      @OA\Response(response=404, description="Ville non trouvée"),
     *      @OA\Response(response=401, description="Non autorisé"),
     *      @OA\Response(response=500, description="Erreur du Serveur")
     * )
     */
    public function destroy(AdType $adType)
    {
        $this->authorize('delete', $adType);


        try {
            $adType->delete();
            return response()->json([
                'message' => 'Supprimée avec succès',
            ], 200); // 200 = OK
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage(),
            ], 500); // 500 = Internal Server Error
        }
    }
}
