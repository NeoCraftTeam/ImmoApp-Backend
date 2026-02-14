<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdStatus;
use App\Http\Requests\AdRequest;
use App\Http\Resources\AdResource as AdApiResource;
use App\Models\Ad;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AdController
{
    use AuthorizesRequests;

    /**
     * Afficher la liste paginée des annonces.
     *
     * @OA\Get(
     *     path="/api/v1/ads",
     *     summary="Obtenir toutes les annonces",
     *     description="Récupérer une liste paginée de toutes les annonces avec leurs relations (utilisateur, quartier, ville, type, images)",
     *     operationId="obtenirAnnonces",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1, default=1, example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15, example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Opération réussie",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Liste des annonces",
     *
     *                 @OA\Items(ref="#/components/schemas/AdResource")
     *             ),
     *
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 description="Liens de pagination",
     *                 @OA\Property(property="first", type="string", example="https://example.com/api/v1/ads?page=1"),
     *                 @OA\Property(property="last", type="string", example="https://example.com/api/v1/ads?page=10"),
     *                 @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                 @OA\Property(property="next", type="string", nullable=true, example="https://example.com/api/v1/ads?page=2")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 description="Métadonnées de pagination",
     *                 @OA\Property(property="current_page", type="integer", example=1, description="Page actuelle"),
     *                 @OA\Property(property="from", type="integer", example=1, description="Premier élément de la page"),
     *                 @OA\Property(property="last_page", type="integer", example=10, description="Dernière page"),
     *                 @OA\Property(property="per_page", type="integer", example=15, description="Éléments par page"),
     *                 @OA\Property(property="to", type="integer", example=15, description="Dernier élément de la page"),
     *                 @OA\Property(property="total", type="integer", example=150, description="Total des éléments")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié - Token d'authentification manquant ou invalide",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Non authentifié"),
     *             @OA\Property(property="error", type="string", example="Token d'authentification requis")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Interdit - Permissions insuffisantes pour voir les annonces",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Cette action n'est pas autorisée"),
     *             @OA\Property(property="error", type="string", example="Permissions insuffisantes")
     *         )
     *     )
     * )
     *
     * @return AnonymousResourceCollection Collection paginée des ressources d'annonces
     *
     * @throws AuthorizationException
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ad::class);

        // P1-3 Fix: Clamp per_page to max 100
        $perPage = min(max((int) request('per_page', config('pagination.per_page', 15)), 1), 100);
        $type = request('type');

        $query = Ad::query()
            ->with('quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'agency')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('status', \App\Enums\AdStatus::AVAILABLE);

        if ($type) {
            $query->whereHas('ad_type', fn($q) => $q->where('name', 'ilike', "%{$type}%"));
        }

        $ads = $query->orderByBoost()->paginate($perPage);

        return AdApiResource::collection($ads);
    }

    /**
     * Créer une nouvelle annonce en base de données.
     *
     * @OA\Post(
     *     path="/api/v1/ads",
     *     summary="Créer une nouvelle annonce",
     *     description="Créer une nouvelle annonce immobilière avec images et données de localisation. Les coordonnées GPS sont obligatoires.",
     *     operationId="creerAnnonce",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données de l'annonce avec images optionnelles",
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"title", "description", "adresse", "price", "surface_area", "bedrooms", "bathrooms", "latitude", "longitude", "quarter_id", "type_id"},
     *
     *                 @OA\Property(property="title", type="string", maxLength=255, example="Magnifique appartement au centre-ville", description="Titre accrocheur de l'annonce"),
     *                 @OA\Property(property="description", type="string", example="Spacieux appartement de 3 pièces avec vue imprenable sur la ville, proche de tous commerces", description="Description détaillée du bien"),
     *                 @OA\Property(property="adresse", type="string", maxLength=500, example="123 Rue de la République, Centre-ville", description="Adresse complète du bien"),
     *                 @OA\Property(property="price", type="number", format="float", minimum=0, example=1200.50, description="Prix en euros"),
     *                 @OA\Property(property="surface_area", type="number", format="float", minimum=0, example=85.5, description="Surface habitable en mètres carrés"),
     *                 @OA\Property(property="bedrooms", type="integer", minimum=0, example=2, description="Nombre de chambres"),
     *                 @OA\Property(property="bathrooms", type="integer", minimum=0, example=1, description="Nombre de salles de bain"),
     *                 @OA\Property(property="has_parking", type="boolean", example=true, description="Disponibilité d'une place de parking"),
     *                 @OA\Property(property="latitude", type="number", format="float", minimum=-90, maximum=90, example=48.8566, description="Latitude GPS (obligatoire)"),
     *                 @OA\Property(property="longitude", type="number", format="float", minimum=-180, maximum=180, example=2.3522, description="Longitude GPS (obligatoire)"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "active", "expired", "sold"}, example="pending", description="Statut de l'annonce (par défaut: pending)"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", example="2024-12-31T23:59:59Z", description="Date d'expiration de l'annonce"),
     *                 @OA\Property(property="quarter_id", type="integer", example=1, description="Identifiant du quartier"),
     *                 @OA\Property(property="type_id", type="integer", example=1, description="Identifiant du type de bien"),
     *                 @OA\Property(property="user_id", type="integer", description="Identifiant du propriétaire (optionnel, par défaut l'utilisateur connecté)", example=1),
     *                 @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     description="Images du bien (maximum 10 images, 5MB chacune). Formats: JPEG, PNG, GIF, WebP. Note: Utiliser le nom de champ 'images[]' pour l'envoi multiple.",
     *
     *                     @OA\Items(type="string", format="binary"),
     *                     maxItems=10
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Annonce créée avec succès",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Annonce créée avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="ad", ref="#/components/schemas/AdResource", description="Données complètes de l'annonce créée"),
     *                 @OA\Property(property="images_count", type="integer", example=3, description="Nombre total d'images associées"),
     *                 @OA\Property(property="images_processed", type="integer", example=3, description="Nombre d'images traitées avec succès")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Non authentifié"),
     *             @OA\Property(property="error", type="string", example="Token d'authentification requis")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Interdit - Permissions insuffisantes pour créer une annonce",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Cette action n'est pas autorisée"),
     *             @OA\Property(property="error", type="string", example="Permissions insuffisantes pour créer une annonce")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreurs de validation ou échec de création",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur lors de la création de l'annonce"),
     *             @OA\Property(property="error", type="string", example="Les coordonnées GPS sont obligatoires"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Erreurs de validation détaillées",
     *                 example={
     *                     "title": {"Le titre est obligatoire"},
     *                     "price": {"Le prix doit être supérieur à 0"}
     *                 }
     *             )
     *         )
     *     )
     * )
     *
     * @param  AdRequest  $request  Les données validées de la requête
     * @return JsonResponse Réponse JSON avec les détails de l'annonce créée
     *
     * @throws Throwable
     */
    public function store(AdRequest $request): JsonResponse
    {
        $this->authorize('create', Ad::class);
        $data = $request->validated();

        DB::beginTransaction();

        try {
            Log::info('Data received for ad creation:', $data);
            Log::info('Files received:', $request->allFiles());

            // Créer l'annonce
            $ad = Ad::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'adresse' => $data['adresse'],
                'price' => $data['price'],
                'surface_area' => $data['surface_area'],
                'bedrooms' => $data['bedrooms'],
                'bathrooms' => $data['bathrooms'],
                'has_parking' => $data['has_parking'] ?? false,
                'location' => Point::makeGeodetic($data['latitude'], $data['longitude']),
                'status' => AdStatus::PENDING->value, // Always start as pending — admin must approve
                'expires_at' => $data['expires_at'],
                'user_id' => auth()->id(), // Always use authenticated user — never trust client input
                'quarter_id' => $data['quarter_id'],
                'type_id' => $data['type_id'],
            ]);

            Log::info('Ad created with ID: ' . $ad->id);

            // Gérer les images via Spatie Media Library
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $ad->addMedia($image)
                        ->toMediaCollection('images');
                }
            }
            // Support backward compatibility or alias
            if ($request->hasFile('image')) {
                $ad->addMediaFromRequest('image')->toMediaCollection('images');
            }
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $ad->addMedia($photo)->toMediaCollection('images');
                }
            }

            DB::commit();

            // Recharger les relations nécessaires pour la réponse
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

            Log::error('Error creating ad: ' . $e->getMessage(), [
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
     *     description="Récupérer les informations détaillées d'une annonce spécifique incluant ses images et relations (utilisateur, quartier, ville, type de bien)",
     *     operationId="obtenirAnnonce",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Identifiant unique de l'annonce",
     *
     *         @OA\Schema(type="string", example="1")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Annonce récupérée avec succès",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/AdResource", description="Données complètes de l'annonce")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié - Token d'authentification manquant ou invalide",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Non authentifié"),
     *             @OA\Property(property="error", type="string", example="Token d'authentification requis")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Interdit - Permissions insuffisantes pour voir cette annonce",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Cette action n'est pas autorisée"),
     *             @OA\Property(property="error", type="string", example="Vous n'avez pas l'autorisation de voir cette annonce")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Annonce introuvable",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Annonce introuvable"),
     *             @OA\Property(property="error", type="string", example="L'annonce avec l'ID spécifié n'existe pas")
     *         )
     *     )
     * )
     *
     * @param  string  $id  L'identifiant de l'annonce
     * @return JsonResponse Réponse JSON avec les données de l'annonce
     */
    public function show(string $id): JsonResponse
    {
        $ad = Ad::with(['media', 'user.agency', 'user.city', 'ad_type', 'quarter.city', 'agency', 'reviews.user'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
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
     *     description="Mettre à jour les informations d'une annonce, ajouter de nouvelles images ou supprimer des images existantes. Tous les champs sont optionnels sauf l'ID.",
     *     operationId="mettreAJourAnnonce",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Identifiant unique de l'annonce à mettre à jour",
     *
     *         @OA\Schema(type="string", example="1")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données mises à jour de l'annonce avec nouvelles images optionnelles",
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(property="_method", type="string", example="PUT", description="Surcharge de méthode HTTP pour les uploads de fichiers"),
     *                 @OA\Property(property="title", type="string", maxLength=255, example="Titre d'annonce mis à jour", description="Nouveau titre de l'annonce"),
     *                 @OA\Property(property="description", type="string", example="Description mise à jour avec nouveaux équipements", description="Nouvelle description détaillée"),
     *                 @OA\Property(property="adresse", type="string", maxLength=500, example="456 Avenue Mise à Jour", description="Nouvelle adresse du bien"),
     *                 @OA\Property(property="price", type="number", format="float", minimum=0, example=1350.75, description="Nouveau prix en euros"),
     *                 @OA\Property(property="surface_area", type="number", format="float", minimum=0, example=90.0, description="Nouvelle surface en m²"),
     *                 @OA\Property(property="bedrooms", type="integer", minimum=0, example=3, description="Nouveau nombre de chambres"),
     *                 @OA\Property(property="bathrooms", type="integer", minimum=0, example=2, description="Nouveau nombre de salles de bain"),
     *                 @OA\Property(property="has_parking", type="boolean", example=false, description="Disponibilité parking mise à jour"),
     *                 @OA\Property(property="latitude", type="number", format="float", minimum=-90, maximum=90, example=48.8606, description="Nouvelle latitude (optionnel - uniquement si localisation changée)"),
     *                 @OA\Property(property="longitude", type="number", format="float", minimum=-180, maximum=180, example=2.3376, description="Nouvelle longitude (optionnel - uniquement si localisation changée)"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "active", "expired", "sold"}, example="active", description="Nouveau statut de l'annonce (optionnel)"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", example="2025-01-31T23:59:59Z", description="Nouvelle date d'expiration (optionnel)"),
     *                 @OA\Property(property="quarter_id", type="integer", example=2, description="Nouvel identifiant de quartier"),
     *                 @OA\Property(property="type_id", type="integer", example=1, description="Nouvel identifiant de type de bien"),
     *                 @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     description="Nouvelles images à ajouter (max 10 total). Note: Utiliser le nom de champ 'images[]'.",
     *
     *                     @OA\Items(type="string", format="binary")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="images_to_delete",
     *                     type="array",
     *                     description="Identifiants des images à supprimer",
     *
     *                     @OA\Items(type="integer"),
     *                     example={1, 3, 5}
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Annonce mise à jour avec succès",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Annonce mise à jour avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="ad", ref="#/components/schemas/AdResource", description="Données mises à jour de l'annonce"),
     *                 @OA\Property(property="images_count", type="integer", example=4, description="Nombre total d'images après mise à jour"),
     *                 @OA\Property(property="new_images_processed", type="integer", example=2, description="Nombre de nouvelles images ajoutées")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié - Token d'authentification manquant ou invalide",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Non authentifié")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Interdit - Permissions insuffisantes pour modifier cette annonce",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Cette action n'est pas autorisée"),
     *             @OA\Property(property="error", type="string", example="Vous n'avez pas l'autorisation de modifier cette annonce")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Annonce introuvable",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Annonce introuvable")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors or update failed",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error updating ad"),
     *             @OA\Property(property="error", type="string", example="Validation failed or server error")
     *         )
     *     )
     * )
     *
     * @param  AdRequest  $request  The validated request data
     *
     * @throws Throwable
     */
    public function update(AdRequest $request, Ad $ad): JsonResponse
    {
        $this->authorize('update', $ad);
        $data = $request->validated();

        try {
            DB::beginTransaction();

            Log::info('Data received for ad update:', $data);
            Log::info('Files received:', $request->allFiles());

            // Mise à jour des coordonnées GPS si fournies
            if (isset($data['latitude']) && isset($data['longitude'])) {
                $data['location'] = Point::makeGeodetic($data['latitude'], $data['longitude']);
            }

            // Validate status transition if status is being changed
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
            }

            // Mettre à jour l'annonce
            $ad->update($data);

            Log::info('Ad updated with ID: ' . $ad->id);

            // Gérer les nouvelles images
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

            // Gérer la suppression d'images existantes
            if ($request->has('images_to_delete') && is_array($request->input('images_to_delete'))) {
                foreach ($request->input('images_to_delete') as $mediaId) {
                    $media = $ad->media()->find($mediaId);
                    if ($media) {
                        $media->delete();
                    }
                }
            }

            Log::info('Media updated for ad ID: ' . $ad->id);

            DB::commit();

            // Recharger les relations
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

            Log::error('Error updating ad: ' . $e->getMessage(), [
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
     *     description="Supprimer définitivement une annonce et toutes ses images associées. Cette action est irréversible et supprimera également tous les fichiers média stockés.",
     *     operationId="supprimerAnnonce",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Identifiant unique de l'annonce à supprimer",
     *
     *         @OA\Schema(type="string", example="1")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Annonce supprimée avec succès",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true, description="Statut de succès de l'opération"),
     *             @OA\Property(property="message", type="string", example="Annonce supprimée avec succès", description="Message de confirmation"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Informations détaillées sur la suppression effectuée",
     *                 @OA\Property(property="deleted_ad_id", type="string", example="1", description="ID de l'annonce supprimée"),
     *                 @OA\Property(property="deleted_ad_title", type="string", example="Magnifique appartement centre-ville", description="Titre de l'annonce supprimée"),
     *                 @OA\Property(property="deleted_images_count", type="integer", example=5, description="Nombre total d'images supprimées"),
     *                 @OA\Property(
     *                     property="deleted_images_details",
     *                     type="array",
     *                     description="Détails des images supprimées",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="id", type="integer", example=1, description="ID de l'image"),
     *                         @OA\Property(property="was_primary", type="boolean", example=true, description="Si l'image était primaire"),
     *                         @OA\Property(property="media_files_deleted", type="integer", example=1, description="Nombre de fichiers média supprimés")
     *                     )
     *                 ),
     *                 @OA\Property(property="deletion_timestamp", type="string", format="date-time", example="2024-01-15T14:30:00.000Z", description="Timestamp de la suppression")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié - Token d'authentification manquant ou invalide",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Non authentifié"),
     *             @OA\Property(property="error", type="string", example="Token d'authentification requis pour supprimer une annonce")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Interdit - Permissions insuffisantes pour supprimer cette annonce",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Cette action n'est pas autorisée"),
     *             @OA\Property(property="error", type="string", example="Vous n'avez pas l'autorisation de supprimer cette annonce. Seul le propriétaire ou un administrateur peut la supprimer.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Annonce introuvable",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Annonce introuvable"),
     *             @OA\Property(property="error", type="string", example="L'annonce avec l'ID spécifié n'existe pas ou a déjà été supprimée")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur lors de la suppression",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur lors de la suppression de l'annonce"),
     *             @OA\Property(property="error", type="string", example="Une erreur s'est produite lors de la suppression des fichiers ou de la base de données"),
     *             @OA\Property(
     *                 property="debug_info",
     *                 type="object",
     *                 nullable=true,
     *                 description="Informations de debug (uniquement en mode debug)",
     *                 @OA\Property(property="file", type="string", example="/path/to/file.php"),
     *                 @OA\Property(property="line", type="integer", example=123),
     *                 @OA\Property(property="ad_id", type="string", example="1")
     *             )
     *         )
     *     )
     * )
     *
     * @param  string  $id  L'identifiant de l'annonce à supprimer
     * @return JsonResponse Réponse JSON confirmant la suppression avec détails
     *
     * @throws Throwable
     */
    public function destroy(string $id): JsonResponse
    {
        $ad = Ad::findOrFail($id);

        $this->authorize('delete', $ad);

        DB::beginTransaction();

        try {
            Log::info('Starting deletion of ad with ID: ' . $id);

            // Compter les images avant suppression pour le rapport
            $imagesCount = $ad->getMedia('images')->count();

            // Supprimer l'annonce (Spatie supprimera automatiquement les fichiers associés)
            $ad->delete();

            Log::info('Ad deleted successfully with ID: ' . $id);

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

            Log::error('Error deleting ad: ' . $e->getMessage(), [
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

    /**
     * Récupérer les annonces à proximité d'une localisation - Recherche publique par coordonnées GPS.
     *
     * @OA\Get(
     *     path="/api/v1/ads/nearby",
     *     summary="Recherche publique d'annonces à proximité par coordonnées GPS",
     *     description="Récupérer toutes les annonces dans un rayon défini autour de coordonnées GPS fournies. Cette route est accessible sans authentification. Les coordonnées GPS (latitude/longitude) sont obligatoires pour cette route.",
     *     operationId="obtenirAnnoncesProximitePublic",
     *     tags={"🏠 Annonces"},
     *
     *     @OA\Parameter(
     *         name="latitude",
     *         in="query",
     *         required=true,
     *         description="Latitude du point central de recherche (obligatoire)",
     *
     *         @OA\Schema(type="number", format="float", minimum=-90, maximum=90, example=48.8566)
     *     ),
     *
     *     @OA\Parameter(
     *         name="longitude",
     *         in="query",
     *         required=true,
     *         description="Longitude du point central de recherche (obligatoire)",
     *
     *         @OA\Schema(type="number", format="float", minimum=-180, maximum=180, example=2.3522)
     *     ),
     *
     *     @OA\Parameter(
     *         name="radius",
     *         in="query",
     *         required=false,
     *         description="Rayon de recherche en mètres (par défaut: 1000m)",
     *
     *         @OA\Schema(type="number", format="float", minimum=0, default=1000, example=5000)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Annonces à proximité récupérées avec succès",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Liste des annonces à proximité triées par distance croissante",
     *
     *                 @OA\Items(ref="#/components/schemas/AdResource")
     *             ),
     *
     *             @OA\Property(
     *                 property="coordinates",
     *                 type="array",
     *                 description="Coordonnées et distances de chaque annonce",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="latitude", type="number", format="float", example=48.8606),
     *                     @OA\Property(property="longitude", type="number", format="float", example=2.3376),
     *                     @OA\Property(property="distance", type="number", format="float", example=523.45)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(
     *                     property="center",
     *                     type="object",
     *                     @OA\Property(property="latitude", type="number", format="float", example=48.8566),
     *                     @OA\Property(property="longitude", type="number", format="float", example=2.3522)
     *                 ),
     *                 @OA\Property(property="radius", type="number", format="float", example=5000),
     *                 @OA\Property(property="count", type="integer", example=12),
     *                 @OA\Property(property="user_id", type="integer", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Interdit - Permissions insuffisantes",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Cette action n'est pas autorisée")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation - Coordonnées manquantes ou invalides",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Latitude and longitude are required and must be within valid ranges.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred while fetching nearby ads.")
     *         )
     *     )
     * )
     *
     * @throws Throwable
     */
    public function ads_nearby_public(AdRequest $request): JsonResponse
    {
        return $this->ads_nearby($request, null);
    }

    /**
     * Logique partagée pour récupérer les annonces à proximité.
     * Fonction privée appelée par ads_nearby_public et ads_nearby_user.
     * Cette fonction ne doit PAS avoir d'annotations Swagger.
     *
     * @throws Throwable
     */
    private function ads_nearby(AdRequest $request, ?string $user = null): JsonResponse
    {
        $this->authorize('adsNearby', Ad::class);

        $defaultRadius = 1000;

        // Si un ID utilisateur est fourni, chercher cet utilisateur
        $targetUser = null;
        if ($user !== null) {
            $targetUser = User::find($user);
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // P0-5 Fix: Prevent IDOR — only allow querying own location or admin
            $authUser = auth()->user();
            if ($authUser && $targetUser->id !== $authUser->id && !$authUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: you can only query your own location.',
                ], 403);
            }
        }

        // Sinon, utiliser l'utilisateur authentifié uniquement si aucun utilisateur cible n'a été fourni
        if ($targetUser === null) {
            $targetUser = auth()->user();
        }

        // Préférer les paramètres de requête s'ils sont présents et valides, sinon utiliser la localisation de l'utilisateur
        $latInput = $request->input('latitude');
        $longInput = $request->input('longitude');

        $lat = is_numeric($latInput) ? (float) $latInput : null;
        $long = is_numeric($longInput) ? (float) $longInput : null;

        // Si pas de coordonnées valides en entrée et qu'on a un utilisateur cible, récupérer via SQL
        if (($lat === null || $long === null) && $targetUser?->id) {
            $row = DB::table('users')
                ->where('id', $targetUser->id)
                ->select([
                    DB::raw('ST_Y(location) as lat'),
                    DB::raw('ST_X(location) as lng'),
                ])
                ->first();
            if ($row) {
                $lat ??= is_numeric($row->lat) ? (float) $row->lat : null;
                $long ??= is_numeric($row->lng) ? (float) $row->lng : null;
            }
        }

        // P0-6 Fix: Clamp radius to max 50km to prevent full-table geo scan DoS
        $radius = min((float) $request->input('radius', $defaultRadius), 50000);

        // Valider la présence et les bornes des coordonnées finales
        $latValid = is_numeric($lat) && $lat >= -90 && $lat <= 90;
        $longValid = is_numeric($long) && $long >= -180 && $long <= 180;

        if (!$latValid || !$longValid) {
            return response()->json([
                'success' => false,
                'message' => 'Latitude and longitude are required and must be within valid ranges.',
            ], 422);
        }

        try {
            // Distance calculée selon le driver (PostgresSQL vs MySQL/MariaDB)
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                $ads = Ad::query()
                    ->whereNotNull('location')
                    ->selectRaw('ad.*')
                    ->selectRaw(
                        'ST_DistanceSphere(location, ST_MakePoint(?, ?)) as distance',
                        [$long, $lat]
                    )
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
                    ->whereNotNull('location')
                    ->selectRaw('ad.*')
                    ->selectRaw(
                        'ST_Distance_Sphere(location, ST_MakePoint(?, ?)) as distance',
                        [$long, $lat]
                    )
                    ->selectRaw('ST_Y(location) as lat')
                    ->selectRaw('ST_X(location) as lng')
                    ->whereRaw('ST_Distance_Sphere(location, ST_MakePoint(?, ?)) <= ?', [$long, $lat, $radius])
                    ->orderBy('distance', 'asc')
                    ->with(['user', 'quarter.city', 'ad_type', 'media'])
                    ->withAvg('reviews', 'rating')
                    ->withCount('reviews')
                    ->get();
            }

            $coordinates = $ads->map(fn(Ad $ad) => [
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
                    'center' => [
                        'latitude' => $lat,
                        'longitude' => $long,
                    ],
                    'radius' => $radius,
                    'count' => $ads->count(),
                    'user_id' => $targetUser?->id,
                ],
            ]);

        } catch (Throwable $e) {
            Log::error('Error in ads_nearby: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching nearby ads.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Récupérer les annonces à proximité de la localisation d'un utilisateur - Route authentifiée.
     *
     * @OA\Get(
     *     path="/api/v1/ads/{user}/nearby",
     *     summary="Recherche d'annonces à proximité d'un utilisateur spécifique",
     *     description="Récupérer toutes les annonces dans un rayon défini autour de la localisation enregistrée d'un utilisateur spécifique. Cette route nécessite une authentification. Si l'utilisateur n'a pas de localisation enregistrée, vous pouvez fournir latitude/longitude en paramètres de requête pour override.",
     *     operationId="obtenirAnnoncesProximiteUtilisateur",
     *     tags={"🏠 Annonces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="Identifiant de l'utilisateur dont on utilise la localisation",
     *
     *         @OA\Schema(type="integer", example=2210)
     *     ),
     *
     *     @OA\Parameter(
     *         name="radius",
     *         in="query",
     *         required=false,
     *         description="Rayon de recherche en mètres (par défaut: 1000m)",
     *
     *         @OA\Schema(type="number", format="float", minimum=0, default=1000, example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="latitude",
     *         in="query",
     *         required=false,
     *         description="Latitude optionnelle pour override la localisation de l'utilisateur",
     *
     *         @OA\Schema(type="number", format="float", minimum=-90, maximum=90, example=48.8566)
     *     ),
     *
     *     @OA\Parameter(
     *         name="longitude",
     *         in="query",
     *         required=false,
     *         description="Longitude optionnelle pour override la localisation de l'utilisateur",
     *
     *         @OA\Schema(type="number", format="float", minimum=-180, maximum=180, example=2.3522)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Annonces à proximité de l'utilisateur récupérées avec succès",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/AdResource")
     *             ),
     *
     *             @OA\Property(
     *                 property="coordinates",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="latitude", type="number", format="float", example=48.8606),
     *                     @OA\Property(property="longitude", type="number", format="float", example=2.3376),
     *                     @OA\Property(property="distance", type="number", format="float", example=0.85)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(
     *                     property="center",
     *                     type="object",
     *                     @OA\Property(property="latitude", type="number", format="float", example=48.8566),
     *                     @OA\Property(property="longitude", type="number", format="float", example=2.3522)
     *                 ),
     *                 @OA\Property(property="radius", type="number", format="float", example=1),
     *                 @OA\Property(property="count", type="integer", example=3),
     *                 @OA\Property(property="user_id", type="integer", example=2210)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Non authentifié")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Interdit - Permissions insuffisantes",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Cette action n'est pas autorisée")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur introuvable",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Latitude and longitude are required and must be within valid ranges.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred while fetching nearby ads.")
     *         )
     *     )
     * )
     *
     * @throws Throwable
     */
    public function ads_nearby_user(AdRequest $request, string $user): JsonResponse
    {
        return $this->ads_nearby($request, $user);
    }

    /**
     * Autocomplétion des champs de recherche (public, sans authentification)
     *
     * Fournit des suggestions basées sur un préfixe pour faciliter la saisie utilisateur.
     * Les suggestions sont retournées avec un compteur du nombre d'annonces "available"
     * qui correspondent à la valeur proposée.
     *
     * Important:
     * - La recherche est un préfixe insensible à la casse ("Pa" trouve "Paris").
     * - Seuls les champs suivants sont pris en charge: city, type, quarter.
     * - Les suggestions sont limitées aux 10 valeurs les plus fréquentes (ordre décroissant par count).
     * - Les compteurs ne prennent en compte que les annonces avec status = 'available'.
     * - Aucune pagination n'est fournie sur cet endpoint.
     *
     * Exemple d'URL: /api/v1/ads/autocomplete?field=city&q=Pa
     *
     * @OA\Get(
     *     path="/api/v1/ads/autocomplete",
     *     summary="Autocomplétion (villes, types, quartiers)",
     *     description="Retourne jusqu'à 10 suggestions commençant par le préfixe fourni pour les champs: ville (city), type de bien (type), quartier (quarter). Les résultats incluent une clé 'value' (libellé) et 'count' (nombre d'annonces disponibles).",
     *     operationId="autocompleteAnnonces",
     *     tags={"🔍 Filtre"},
     *
     *     @OA\Parameter(
     *         name="field",
     *         in="query",
     *         required=true,
     *         description="Champ ciblé pour l'autocomplétion",
     *
     *         @OA\Schema(type="string", enum={"city", "type", "quarter"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=false,
     *         description="Préfixe à rechercher (insensible à la casse). Si omis, retourne les 10 valeurs les plus fréquentes.",
     *
     *         @OA\Schema(type="string", example="Pa")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste de suggestions pour l'autocomplétion",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Jusqu'à 10 éléments triés par 'count' décroissant",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="value", type="string", example="Paris", description="Libellé proposé (ville / type / quartier)"),
     *                     @OA\Property(property="count", type="integer", example=42, description="Nombre d'annonces avec status 'available' associées à cette valeur")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Paramètre 'field' invalide ou non supporté",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid field. Allowed values: city, type, quarter.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur lors de la récupération des suggestions",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching autocomplete suggestions")
     *         )
     *     ),
     *
     *     @OA\Examples(
     *         example="Ville - préfixe Pa",
     *         summary="Suggestions pour city=Pa",
     *         value={
     *             "request": "/api/v1/ads/autocomplete?field=city&q=Pa",
     *             "response": {
     *                 "success": true,
     *                 "data": {
     *                     {"value": "Paris", "count": 128},
     *                     {"value": "Pamiers", "count": 9}
     *                 }
     *             }
     *         }
     *     ),
     *     @OA\Examples(
     *         example="Type - préfixe Ap",
     *         summary="Suggestions pour type=Ap",
     *         value={
     *             "request": "/api/v1/ads/autocomplete?field=type&q=Ap",
     *             "response": {
     *                 "success": true,
     *                 "data": {
     *                     {"value": "Appartement", "count": 312}
     *                 }
     *             }
     *         }
     *     ),
     *     @OA\Examples(
     *         example="Quartier - sans q",
     *         summary="Top 10 quartiers les plus fréquents (sans préfixe)",
     *         value={
     *             "request": "/api/v1/ads/autocomplete?field=quarter",
     *             "response": {
     *                 "success": true,
     *                 "data": {
     *                     {"value": "Centre", "count": 67},
     *                     {"value": "Vieux-Port", "count": 51}
     *                 }
     *             }
     *         }
     *     )
     * )
     */
    public function autocomplete(AdRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $field = $validated['field'] ?? null;
        $q = (string) ($validated['q'] ?? '');

        if (!in_array($field, ['city', 'type', 'quarter'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid field. Allowed values: city, type, quarter.',
            ], 422);
        }

        try {
            $driver = DB::getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';
            $prefix = $q !== '' ? ($q . '%') : '%';

            // Build base query per field, counting only available ads
            if ($field === 'city') {
                $rows = DB::table('city')
                    ->join('quarter', 'quarter.city_id', '=', 'city.id')
                    ->join('ad', function ($join): void {
                        $join->on('ad.quarter_id', '=', 'quarter.id')
                            ->where('ad.status', '=', 'available');
                    })
                    ->when($q !== '', function ($query) use ($likeOperator, $prefix): void {
                        $query->where('city.name', $likeOperator, $prefix);
                    })
                    ->groupBy('city.name')
                    ->select(['city.name as value', DB::raw('COUNT(ad.id) as count')])
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get();
            } elseif ($field === 'type') {
                $rows = DB::table('ad_type')
                    ->join('ad', function ($join): void {
                        $join->on('ad.type_id', '=', 'ad_type.id')
                            ->where('ad.status', '=', 'available');
                    })
                    ->when($q !== '', function ($query) use ($likeOperator, $prefix): void {
                        $query->where('ad_type.name', $likeOperator, $prefix);
                    })
                    ->groupBy('ad_type.name')
                    ->select(['ad_type.name as value', DB::raw('COUNT(ad.id) as count')])
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get();
            } else { // quarter
                $rows = DB::table('quarter')
                    ->join('ad', function ($join): void {
                        $join->on('ad.quarter_id', '=', 'quarter.id')
                            ->where('ad.status', '=', 'available');
                    })
                    ->when($q !== '', function ($query) use ($likeOperator, $prefix): void {
                        $query->where('quarter.name', $likeOperator, $prefix);
                    })
                    ->groupBy('quarter.name')
                    ->select(['quarter.name as value', DB::raw('COUNT(ad.id) as count')])
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (Throwable $e) {
            Log::error('Autocomplete error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching autocomplete suggestions',
            ], 500);
        }
    }

    /**
     * Obtenir les facettes (compteurs) pour les filtres
     *
     * Agrège côté base de données les compteurs utiles pour construire l'UI de filtres.
     * Aucun paramètre n'est requis. Endpoint public (sans authentification).
     * Seules les annonces avec status = "available" sont comptabilisées.
     *
     * Structure de la réponse:
     * - cities:     Top 20 villes par nombre d'annonces disponibles [{ name, count }]
     * - types:      Top 20 types de bien [{ name, count }]
     * - bedrooms:   Toutes les valeurs présentes, triées numériquement croissant [{ value, count }]
     * - price_range:    bornes min/max (valeurs sous forme de chaînes décimales)
     * - surface_range:  bornes min/max (valeurs sous forme de chaînes décimales)
     * - has_parking:    répartition avec/sans parking { with_parking, without_parking }
     *
     * @OA\Get(
     *     path="/api/v1/ads/facets",
     *     summary="Obtenir les facettes (compteurs de filtres)",
     *     description="Retourne les compteurs agrégés pour alimenter les filtres (villes, types, chambres, plages de prix/surface, parking). Endpoint public, sans authentification.",
     *     operationId="facettesAnnonces",
     *     tags={"🔍 Filtre"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Facettes récupérées avec succès",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="cities",
     *                     type="array",
     *                     description="Top 20 villes par nombre d'annonces disponibles",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="name", type="string", example="Paris"),
     *                         @OA\Property(property="count", type="integer", example=128)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="types",
     *                     type="array",
     *                     description="Top 20 types de bien",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="name", type="string", example="Appartement"),
     *                         @OA\Property(property="count", type="integer", example=312)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="bedrooms",
     *                     type="array",
     *                     description="Répartition par nombre de chambres (tri croissant)",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="value", type="integer", example=2),
     *                         @OA\Property(property="count", type="integer", example=105)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="price_range",
     *                     type="object",
     *                     description="Plage de prix (valeurs décimales en chaîne)",
     *                     @OA\Property(property="min", type="string", example="25000.00"),
     *                     @OA\Property(property="max", type="string", example="150000.00")
     *                 ),
     *                 @OA\Property(
     *                     property="surface_range",
     *                     type="object",
     *                     description="Plage de surface (valeurs décimales en chaîne)",
     *                     @OA\Property(property="min", type="string", example="31.00"),
     *                     @OA\Property(property="max", type="string", example="120.00")
     *                 ),
     *                 @OA\Property(
     *                     property="has_parking",
     *                     type="object",
     *                     description="Répartition des annonces avec/sans parking",
     *                     @OA\Property(property="with_parking", type="integer", example=511),
     *                     @OA\Property(property="without_parking", type="integer", example=489)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur lors de la récupération des facettes",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching facets")
     *         )
     *     ),
     *
     *     @OA\Examples(
     *         example="Réponse de succès",
     *         summary="Exemple de payload retourné",
     *         value={
     *             "success": true,
     *             "data": {
     *                 "cities": {
     *                     {"name": "Paris", "count": 128},
     *                     {"name": "Lyon", "count": 64}
     *                 },
     *                 "types": {
     *                     {"name": "Appartement", "count": 312},
     *                     {"name": "Maison", "count": 198}
     *                 },
     *                 "bedrooms": {
     *                     {"value": 1, "count": 109},
     *                     {"value": 2, "count": 105}
     *                 },
     *                 "price_range": {"min": "25000.00", "max": "150000.00"},
     *                 "surface_range": {"min": "31.00", "max": "120.00"},
     *                 "has_parking": {"with_parking": 511, "without_parking": 489}
     *             }
     *         }
     *     )
     * )
     */
    public function facets(): JsonResponse
    {
        try {
            $driver = DB::getDriverName();
            // Choisir une expression de CAST compatible selon le SGBD
            $bedroomsCast = match ($driver) {
                'pgsql' => 'CAST(bedrooms as integer)',
                'sqlite' => 'CAST(bedrooms as integer)',
                default => 'CAST(bedrooms as signed)', // MySQL / MariaDB
            };

            // Villes (top 20 par nombre d'annonces disponibles)
            $cities = DB::table('ad')
                ->join('quarter', 'ad.quarter_id', '=', 'quarter.id')
                ->join('city', 'quarter.city_id', '=', 'city.id')
                ->where('ad.status', '=', 'available')
                ->whereNotNull('city.name')
                ->groupBy('city.name')
                ->select(['city.name as name', DB::raw('COUNT(*) as count')])
                ->orderByDesc('count')
                ->limit(20)
                ->get();

            // Types (top 20)
            $types = DB::table('ad')
                ->join('ad_type', 'ad.type_id', '=', 'ad_type.id')
                ->where('ad.status', '=', 'available')
                ->whereNotNull('ad_type.name')
                ->groupBy('ad_type.name')
                ->select(['ad_type.name as name', DB::raw('COUNT(*) as count')])
                ->orderByDesc('count')
                ->limit(20)
                ->get();

            // Chambres (toutes les valeurs présentes, tri croissant)
            $bedrooms = DB::table('ad')
                ->where('status', '=', 'available')
                ->whereNotNull('bedrooms')
                ->groupBy('bedrooms')
                ->select([DB::raw($bedroomsCast . ' as value'), DB::raw('COUNT(*) as count')])
                ->orderBy('value')
                ->get();

            // Plages min/max (ignorer les NULL)
            $priceRange = DB::table('ad')
                ->where('status', '=', 'available')
                ->whereNotNull('price')
                ->selectRaw('MIN(price) as min, MAX(price) as max')
                ->first();

            $surfaceRange = DB::table('ad')
                ->where('status', '=', 'available')
                ->whereNotNull('surface_area')
                ->selectRaw('MIN(surface_area) as min, MAX(surface_area) as max')
                ->first();

            // Parking (attention aux différences booléennes entre SGBD)
            $withParking = DB::table('ad')
                ->where('status', '=', 'available')
                ->where('has_parking', '=', $driver === 'pgsql' ? DB::raw('TRUE') : 1)
                ->count();

            $withoutParking = DB::table('ad')
                ->where('status', '=', 'available')
                ->where('has_parking', '=', $driver === 'pgsql' ? DB::raw('FALSE') : 0)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'cities' => $cities,
                    'types' => $types,
                    'bedrooms' => $bedrooms,
                    'price_range' => [
                        'min' => $priceRange?->min,
                        'max' => $priceRange?->max,
                    ],
                    'surface_range' => [
                        'min' => $surfaceRange?->min,
                        'max' => $surfaceRange?->max,
                    ],
                    'has_parking' => [
                        'with_parking' => $withParking,
                        'without_parking' => $withoutParking,
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Facets error: ' . $e->getMessage(), [
                'driver' => DB::getDriverName(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching facets',
            ], 500);
        }
    }

    /**
     * Rechercher des annonces avec filtres multiples
     *
     * @OA\Get(
     *     path="/api/v1/ads/search",
     *     summary="Rechercher des annonces",
     *     description="Rechercher des annonces avec filtres multiples : texte, ville, type, chambres, prix, surface, parking",
     *     operationId="rechercherAnnonces",
     *     tags={"🔍 Filtre"},
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Terme de recherche textuel",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="appartement lumineux")
     *     ),
     *
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="Filtrer par ville",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Paris")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Champ de tri",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"price", "surface_area", "created_at"}, example="price")
     *     ),
     *
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="Ordre de tri",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="asc")
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Résultats de recherche",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdResource")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function search(AdRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // P1-3 Fix: Force fallback if not using Meilisearch (collection driver ignores filters/sort callbacks)
            if (config('scout.driver') !== 'meilisearch') {
                return $this->searchFallback($validated);
            }

            // Paramètres de recherche
            $q = (string) ($validated['q'] ?? '');
            $city = $validated['city'] ?? null;
            $type = $validated['type'] ?? null;
            $typeId = $validated['type_id'] ?? null;
            $quarterId = $validated['quarter_id'] ?? null;

            // Filtres numériques
            $minBedrooms = isset($validated['bedrooms']) ? (int) $validated['bedrooms'] : null;
            $minBathrooms = isset($validated['bathrooms']) ? (int) $validated['bathrooms'] : null;
            $minPrice = isset($validated['price_min']) ? (float) $validated['price_min'] : null;
            $maxPrice = isset($validated['price_max']) ? (float) $validated['price_max'] : null;
            $minSurface = isset($validated['surface_min']) ? (float) $validated['surface_min'] : null;
            $maxSurface = isset($validated['surface_max']) ? (float) $validated['surface_max'] : null;
            $hasParking = isset($validated['has_parking']) ? (bool) $validated['has_parking'] : null;

            // Tri et pagination
            $sortBy = $validated['sort'] ?? 'created_at';
            $sortOrder = strtolower($validated['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            // P1-3 Fix: Clamp per_page to max 100
            $perPage = min(max((int) ($validated['per_page'] ?? config('pagination.per_page', 15)), 1), 100);

            // Construire les filtres Meilisearch
            $filters = [];

            // Filtres de chaînes
            if (!empty($city)) {
                $filters[] = sprintf("city = '%s'", str_replace("'", "\\'", $city));
            }
            if (!empty($type)) {
                $filters[] = sprintf("type = '%s'", str_replace("'", "\\'", $type));
            }

            // Filtres d'ID
            if (!empty($typeId)) {
                $filters[] = sprintf('type_id = %d', (int) $typeId);
            }
            if (!empty($quarterId)) {
                $filters[] = sprintf('quarter_id = %d', (int) $quarterId);
            }

            // Filtres de chambres et salles de bain
            if ($minBedrooms !== null) {
                $filters[] = sprintf('bedrooms >= %d', $minBedrooms);
            }

            if ($minBathrooms !== null) {
                $filters[] = sprintf('bathrooms >= %d', $minBathrooms);
            }

            // Filtres de prix
            if ($minPrice !== null) {
                $filters[] = sprintf('price >= %f', $minPrice);
            }
            if ($maxPrice !== null) {
                $filters[] = sprintf('price <= %f', $maxPrice);
            }

            // Filtres de surface
            if ($minSurface !== null) {
                $filters[] = sprintf('surface_area >= %f', $minSurface);
            }
            if ($maxSurface !== null) {
                $filters[] = sprintf('surface_area <= %f', $maxSurface);
            }

            // Filtre parking
            if ($hasParking !== null) {
                $filters[] = sprintf('has_parking = %s', $hasParking ? 'true' : 'false');
            }

            // Toujours filtrer par status available (si tu veux)
            $filters[] = "status = 'available'";

            // Whitelist des champs de tri
            $allowedSorts = ['price', 'surface_area', 'created_at', 'boost_score'];

            // Construire la requête Scout
            $builder = Ad::search($q, function (\Meilisearch\Endpoints\Indexes $index, string $query, array $options) use ($filters, $sortBy, $sortOrder, $allowedSorts) {
                // AND logic : tous les filtres doivent matcher
                $options['filter'] = implode(' AND ', $filters);

                // Tri
                if (!in_array($sortBy, $allowedSorts, true)) {
                    // Tri par défaut : Boost Score DESC puis Created At DESC
                    $options['sort'] = ['boost_score:desc', 'created_at:desc'];
                } else {
                    $options['sort'] = [sprintf('%s:%s', $sortBy, $sortOrder)];
                }

                return $index->search($query, $options);
            })
                // Eager load des relations
                ->query(fn($eloquent) => $eloquent->with(['quarter.city', 'ad_type', 'media', 'user'])->withAvg('reviews', 'rating')->withCount('reviews'));

            // Paginer
            $results = $builder->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => AdApiResource::collection($results->items()),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                ],
                'links' => [
                    'first' => $results->url(1),
                    'last' => $results->url($results->lastPage()),
                    'prev' => $results->previousPageUrl(),
                    'next' => $results->nextPageUrl(),
                ],
            ], 200);
        } catch (\Meilisearch\Exceptions\ApiException | \Exception $e) {
            Log::warning('Search fallback to Eloquent: ' . $e->getMessage());

            return $this->searchFallback($validated);
        }
    }

    /**
     * Fallback Eloquent search when Meilisearch is unavailable.
     *
     * @param  array<string, mixed>  $validated
     */
    private function searchFallback(array $validated): JsonResponse
    {
        $q = (string) ($validated['q'] ?? '');
        $city = $validated['city'] ?? null;
        $type = $validated['type'] ?? null;
        // P1-3 Fix: Clamp per_page to max 100
        $perPage = min(max((int) ($validated['per_page'] ?? config('pagination.per_page', 15)), 1), 100);
        $minBedrooms = isset($validated['bedrooms']) ? (int) $validated['bedrooms'] : null;
        $minPrice = isset($validated['price_min']) ? (float) $validated['price_min'] : null;
        $maxPrice = isset($validated['price_max']) ? (float) $validated['price_max'] : null;
        $minSurface = isset($validated['surface_min']) ? (float) $validated['surface_min'] : null;
        $maxSurface = isset($validated['surface_max']) ? (float) $validated['surface_max'] : null;
        $hasParking = isset($validated['has_parking']) ? (bool) $validated['has_parking'] : null;

        $query = Ad::query()
            ->with(['quarter.city', 'ad_type', 'media', 'user'])
            ->where('status', \App\Enums\AdStatus::AVAILABLE);

        if ($q) {
            $query->where(function ($qb) use ($q): void {
                $qb->where('title', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%")
                    ->orWhere('adresse', 'ilike', "%{$q}%");
            });
        }

        if ($city) {
            $query->whereHas('quarter.city', fn($qb) => $qb->where('name', 'ilike', "%{$city}%"));
        }

        if ($type) {
            $query->whereHas('ad_type', fn($qb) => $qb->where('name', 'ilike', "%{$type}%"));
        }

        if ($minBedrooms !== null) {
            $query->where('bedrooms', '>=', $minBedrooms);
        }

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        if ($minSurface !== null) {
            $query->where('surface_area', '>=', $minSurface);
        }

        if ($maxSurface !== null) {
            $query->where('surface_area', '<=', $maxSurface);
        }

        // Tri et pagination
        $sortBy = $validated['sort'] ?? 'created_at';
        $sortOrder = strtolower($validated['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($hasParking) {
            $query->where('has_parking', true);
        }

        // Appliquer le tri
        $allowedSorts = ['price', 'surface_area', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByBoost();
        }

        $results = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AdApiResource::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
            'links' => [
                'first' => $results->url(1),
                'last' => $results->url($results->lastPage()),
                'prev' => $results->previousPageUrl(),
                'next' => $results->nextPageUrl(),
            ],
        ], 200);
    }
}
