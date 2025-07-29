<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     title="KeyHome api",
 *     version="v1",
 *     description="Documentation complète de KeyHome api",
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 *     )
 * 
 * 
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     in="header",
 *     name="Authorization",
 *     bearerFormat="JWT"
 * )
 */
class welcomeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     operationId="index",
     *     security={{"bearerAuth":{}}},
     *     tags={"Users"},
     *     summary="Liste des utilisateurs",
     *     description="Récupère la liste paginée des utilisateurs",
     *     @OA\Response(response=200, description="Succès"),
     *     @OA\Response(response=401, description="Non autorisé"),
     *     @OA\Response(response=404, description="Non trouvé"),
     *     @OA\Response(response=500, description="Erreur du Serveur"),
     * )
     */
    public function index()
    {
        return response()->json(['Utilisateurs' => [
            [
                'name' => 'Jorel KUE',
                'email' => 'jorel@example.com',
                'age' => 30,
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'Anytown',
                    'state' => 'CA',
                    'zip' => '12345'
                ]
            ],
            [
                'name' => 'Cédrick FEZE',
                'email' => 'cedrick@example.com',
                'age' => 25,
                'address' => [
                    'street' => '456 Elm St',
                    'city' => 'Othertown',
                    'state' => 'NY',
                    'zip' => '67890'
                ]
            ],
            [
                'name' => 'Stéphane KAMGA',
                'email' => 'stephane@example.com',
                'age' => 40,
                'address' => [
                    'street' => '789 Oak St',
                    'city' => 'Somewhere',
                    'state' => 'TX',
                    'zip' => '54321'
                ]
            ]
        ]]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     summary="Afficher un utilisateur",
     *     operationId="show",
     *     tags={"user"},
     *     @OA\Parameter(
     *           name="id",
     *           description="id de l'utitisateur",
     *           required=true,
     *           in="path",
     *           @OA\Schema(
     *               type="integer"
     *           )
     *       ),
     *     @OA\Response(response=200, description="Succès")
     * )
     */
    public function show(string $id)
    {
        return response()->json(
            [
                'name' => 'Jorel KUE',
                'email' => 'jorel@example.com',
                'age' => 30,
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'Anytown',
                    'state' => 'CA',
                    'zip' => '12345'

                ],

            ]
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
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
