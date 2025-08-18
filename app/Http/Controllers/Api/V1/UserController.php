<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CityRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController
{
    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     operationId="ShowUsers",
     *     security={{"bearerAuth":{}}},
     *     tags={"Users"},
     *     summary="Liste des utilisateurs",
     *     description="Récupère la liste paginée des utilisateurs",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Succès",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non autorisé"),
     *     @OA\Response(response=404, description="Non trouvé"),
     *     @OA\Response(response=500, description="Erreur du Serveur")
     * )
     */
    public function index()
    {
        $users = User::paginate(10);
        return UserResource::collection($users);
    }

    public function store(Request $request)
    {

    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     operationId="showUser",
     *     security={{"bearerAuth":{}}},
     *     tags={"Users"},
     *     summary="Afficher un utilisateur",
     *     description="Récupère les détails d'un utilisateur spécifique",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Succès",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(response=404, description="Utilisateur non trouvé"),
     *     @OA\Response(response=401, description="Non autorisé"),
     *     @OA\Response(response=500, description="Erreur du Serveur"),
     * )
     */
    public function show(User $id)
    {
        return new UserResource($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CityRequest $request, User $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
