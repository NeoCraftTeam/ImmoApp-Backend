<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\QuarterRequest;
use App\Http\Resources\QuarterResource;
use App\Models\Quarter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

class QuarterController
{
    use AuthorizesRequests;

    /**
     * Récupère la liste paginée des quartiers
     *
     * @return AnonymousResourceCollection
     *
     * @OA\Get(
     *     path="/api/v1/quarters",
     *     summary="Liste des quartiers",
     *     description="Récupère la liste paginée de tous les quartiers",
     *     tags={"📍 Quartier"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des quartiers récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Quarter")
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 @OA\Property(property="first", type="string"),
     *                 @OA\Property(property="last", type="string"),
     *                 @OA\Property(property="prev", type="string", nullable=true),
     *                 @OA\Property(property="next", type="string", nullable=true)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="to", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès interdit",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cette action n'est pas autorisée.")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $quarter = Quarter::paginate(config('pagination.default', 10));
        return QuarterResource::collection($quarter);
    }

    /**
     * Crée un nouveau quartier
     *
     * @param QuarterRequest $request
     * @return JsonResponse
     *
     * @OA\Post(
     *     path="/api/v1/quarters",
     *     summary="Créer un quartier",
     *     description="Crée un nouveau quartier dans une ville",
     *     tags={"📍 Quartier"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "city_id"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="Nom du quartier",
     *                 example="Centre-ville"
     *             ),
     *             @OA\Property(
     *                 property="city_id",
     *                 type="integer",
     *                 description="ID de la ville parente",
     *                 example=1
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Quartier créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Creation réussie"),
     *             @OA\Property(property="data", ref="#/components/schemas/Quarter")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflit - Le quartier existe déjà",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cette ce quartier existe déjà.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="Le champ nom est obligatoire.")
     *                 ),
     *                 @OA\Property(
     *                     property="city_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="Le champ city_id est obligatoire.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Erreur de creation"),
     *             @OA\Property(property="error", type="string", example="Database connection failed")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(QuarterRequest $request)
    {
        $this->authorize('create', Quarter::class);
        $data = $request->validated();

        if (Quarter::where('name', $data['name'])->exists()) {
            return response()->json([
                'message' => 'Cette ce quartier existe déjà.'
            ], 409);
        }


        try {
            $quarter = Quarter::create([
                "name" => $data['name'],
                "city_id" => $data['city_id'],
            ]);
            return response()->json([
                "message" => "Creation réussie",
                "data" => new QuarterResource($quarter),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                "message" => "Erreur de creation",
                "error" => $e->getMessage(),
            ]);
        }
    }

    /**
     * Affiche un quartier spécifique
     *
     * @param string $id
     * @return JsonResponse|QuarterResource
     *
     * @OA\Get(
     *     path="/api/v1/quarters/{id}",
     *     summary="Afficher un quartier",
     *     description="Récupère les détails d'un quartier spécifique",
     *     tags={"📍 Quartier"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du quartier",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du quartier",
     *         @OA\JsonContent(ref="#/components/schemas/Quarter")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quartier non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Ce quartier n'existe pas")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès interdit",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cette action n'est pas autorisée.")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show(string $id)
    {
        $quarterId = Quarter::find($id);
        if (!$quarterId) {
            return response()->json([
                'message' => 'Ce quartier n\'existe pas',
            ], 404);
        }
        return new QuarterResource($quarterId);
    }

    /**
     * Met à jour un quartier existant
     *
     * @param QuarterRequest $request
     * @param Quarter $quarter
     * @return JsonResponse
     *
     * @OA\Put(
     *     path="/api/v1/quarters/{quarter}",
     *     summary="Mettre à jour un quartier",
     *     description="Met à jour les informations d'un quartier existant",
     *     tags={"📍 Quartier"},
     *     @OA\Parameter(
     *         name="quarter",
     *         in="path",
     *         description="ID du quartier à mettre à jour",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="Nom du quartier",
     *                 example="Centre-ville Nord"
     *             ),
     *             @OA\Property(
     *                 property="city_id",
     *                 type="integer",
     *                 description="ID de la ville parente",
     *                 example=1
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quartier mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Mis à jour avec succès."),
     *             @OA\Property(property="quarter", ref="#/components/schemas/Quarter")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflit - Le nom est déjà utilisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Ce nom de quartier est déjà utilisé.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quartier non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Quarter]")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties=@OA\AdditionalProperties(
     *                     type="array",
     *                     @OA\Items(type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Erreur de mise à jour"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(QuarterRequest $request, Quarter $quarter)
    {
        $this->authorize('update', $quarter);
        $data = $request->validated();

        try {
            // Vérifier si un autre quarter utilise déjà ce nom
            if (isset($data['name']) &&
                Quarter::where('name', $data['name'])
                    ->where('id', '!=', $quarter->id)
                    ->exists()) {
                return response()->json([
                    'message' => 'Ce nom de quartier est déjà utilisé.'
                ], 409);
            }

            $quarter->update($data);
            $quarter->load('city');

            return response()->json([
                'message' => 'Mis à jour avec succès.',
                'quarter' => new QuarterResource($quarter), // 'user' -> 'quarter'
            ]);

        } catch (Throwable $e) {
            return response()->json([
                "message" => "Erreur de mise à jour",
                "error" => $e->getMessage(),
            ], 500); // Ajouter le code d'erreur
        }
    }

    /**
     * Supprime un quartier
     *
     * @param Quarter $quarter
     * @return JsonResponse
     *
     * @OA\Delete(
     *     path="/api/v1/quarters/{quarter}",
     *     summary="Supprimer un quartier",
     *     description="Supprime définitivement un quartier",
     *     tags={"📍 Quartier"},
     *     @OA\Parameter(
     *         name="quarter",
     *         in="path",
     *         description="ID du quartier à supprimer",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quartier supprimé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Quartier supprimé avec succès.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quartier non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Quarter]")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès interdit",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cette action n'est pas autorisée.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Erreur de suppression"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function destroy(Quarter $quarter)
    {
        $this->authorize('delete', $quarter);

        try {

            $deleted = $quarter->delete();

            if (!$quarter) {
                return response()->json([
                    'message' => 'Ce quartier n\'existe pas,'
                ], 404);
            }

            return response()->json([
                'message' => 'Quartier supprimé avec succès.'
            ], 200);

        } catch (Throwable $e) {
            return response()->json([
                "message" => "Erreur de suppression",
                "error" => $e->getMessage(),
            ], 500);
        }
    }
}
