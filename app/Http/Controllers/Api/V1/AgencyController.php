<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agency\CreateAgencyAction;
use App\Actions\Agency\DeleteAgencyAction;
use App\Actions\Agency\ListAgenciesAction;
use App\Actions\Agency\UpdateAgencyAction;
use App\Http\Requests\AgencyRequest;
use App\Http\Resources\AgencyResource;
use App\Models\Agency;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class AgencyController
{
    use AuthorizesRequests;

    /**
     * @OA\Get(
     *     path="/api/v1/agencies",
     *     operationId="listAgencies",
     *     security={{"bearerAuth":{}}},
     *     tags={"🏢 Agence"},
     *     summary="Liste des agences",
     *     description="Récupère la liste paginée des agences",
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Succès",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Agency"))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non autorisé")
     * )
     */
    public function index(ListAgenciesAction $listAgencies)
    {
        $this->authorize('viewAny', Agency::class);

        return $listAgencies->handle();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/agencies",
     *     operationId="storeAgency",
     *     security={{"bearerAuth":{}}},
     *     tags={"🏢 Agence"},
     *     summary="Créer une agence",
     *     description="Crée une nouvelle agence",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *              @OA\Schema(
     *                 required={"name"},
     *
     *                 @OA\Property(property="name", type="string", example="Mon Agence Immo"),
     *                 @OA\Property(property="logo", type="string", format="binary", description="Logo de l'agence")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Agence créée",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Agency")
     *     ),
     *
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non autorisé")
     * )
     */
    public function store(AgencyRequest $request, CreateAgencyAction $createAgency)
    {
        $this->authorize('create', Agency::class);

        $agency = $createAgency->handle($request->validated());

        return new AgencyResource($agency);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/agencies/{id}",
     *     operationId="showAgency",
     *     security={{"bearerAuth":{}}},
     *     tags={"🏢 Agence"},
     *     summary="Afficher une agence",
     *     description="Détails d'une agence spécifique",
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
     *         @OA\JsonContent(ref="#/components/schemas/Agency")
     *     ),
     *
     *     @OA\Response(response=404, description="Agence non trouvée")
     * )
     */
    public function show(Agency $agency)
    {
        $this->authorize('view', $agency);

        return new AgencyResource($agency);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/agencies/{id}",
     *     operationId="updateAgency",
     *     security={{"bearerAuth":{}}},
     *     tags={"🏢 Agence"},
     *     summary="Mettre à jour une agence",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Agence mise à jour",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Agency")
     *     ),
     *
     *     @OA\Response(response=404, description="Agence non trouvée")
     * )
     */
    public function update(AgencyRequest $request, Agency $agency, UpdateAgencyAction $updateAgency)
    {
        $this->authorize('update', $agency);

        $agency = $updateAgency->handle($agency, $request->validated());

        return new AgencyResource($agency);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/agencies/{id}",
     *     operationId="deleteAgency",
     *     security={{"bearerAuth":{}}},
     *     tags={"🏢 Agence"},
     *     summary="Supprimer une agence",
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
     *         description="Agence supprimée"
     *     ),
     *     @OA\Response(response=404, description="Agence non trouvée")
     * )
     */
    public function destroy(Agency $agency, DeleteAgencyAction $deleteAgency)
    {
        $this->authorize('delete', $agency);

        $deleteAgency->handle($agency);

        return response()->json();
    }
}
