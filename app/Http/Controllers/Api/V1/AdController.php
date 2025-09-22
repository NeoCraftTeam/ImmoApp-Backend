<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AdRequest;
use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Models\AdImage;
use Clickbar\Magellan\Data\Geometries\Point;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AdController
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
     *     tags={"Annonces"},
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
     *                 @OA\Property(property="first", type="string", example="http://example.com/api/v1/ads?page=1"),
     *                 @OA\Property(property="last", type="string", example="http://example.com/api/v1/ads?page=10"),
     *                 @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                 @OA\Property(property="next", type="string", nullable=true, example="http://example.com/api/v1/ads?page=2")
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
        $ads = Ad::with('quarter.city', 'ad_type', 'images', 'user')->paginate(config('pagination.per_page', 15));

        return AdResource::collection($ads);
    }

    /**
     * Créer une nouvelle annonce en base de données.
     *
     * @OA\Post(
     *     path="/api/v1/ads",
     *     summary="Créer une nouvelle annonce",
     *     description="Créer une nouvelle annonce immobilière avec images et données de localisation. Les coordonnées GPS sont obligatoires.",
     *     operationId="creerAnnonce",
     *     tags={"Annonces"},
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
     *                     description="Images du bien (maximum 10 images, 5MB chacune). Formats acceptés: JPEG, PNG, GIF, WebP",
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

            // Validation des coordonnées GPS
            if (! isset($data['latitude']) || ! isset($data['longitude'])) {
                throw new Exception('Latitude and longitude are required');
            }

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
                'status' => $data['status'] ?? 'pending', // Utiliser le status du request
                'expires_at' => $data['expires_at'],
                'user_id' => $data['user_id'] ?? auth()->id(), // Utiliser user_id du request si présent
                'quarter_id' => $data['quarter_id'],
                'type_id' => $data['type_id'],
            ]);

            Log::info('Ad created with ID: '.$ad->id);

            // Gérer les images
            $imagesProcessed = 0;
            if (! empty($request->allFiles())) {
                $imagesProcessed = $this->handleImageUpload($request, $ad);
            }

            Log::info('Images processed: '.$imagesProcessed);

            DB::commit();

            // Charger les relations
            $ad->load([
                'images',
                'user',
                'ad_type',
                'quarter.city',
            ]);

            // Recompter les images après chargement
            $actualImagesCount = $ad->images()->count();
            Log::info('Actual images count after loading: '.$actualImagesCount);

            return response()->json([
                'success' => true,
                'message' => 'Ad created successfully',
                'data' => [
                    'ad' => new AdResource($ad),
                    'images_count' => $actualImagesCount,
                    'images_processed' => $imagesProcessed,
                ],
            ], 201);

        } catch (Throwable $e) {
            DB::rollback();

            Log::error('Error creating ad: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'data' => $data,
                'files' => $request->allFiles(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating ad',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while creating the ad.',
            ], 422);
        }
    }

    /**
     * Gérer le téléchargement des images
     */
    private function handleImageUpload($request, Ad $ad): int
    {
        Log::info('Starting image upload process (filesystem mode)');

        $images = [];
        $imagesProcessed = 0;

        // 1) Récupérer tous les fichiers envoyés et aplatir les structures imbriquées
        $allFiles = $request->allFiles();
        $detectedKeys = array_keys($allFiles);
        Log::info('All file keys detected in request:', $detectedKeys);

        $flatten = function ($files) use (&$flatten) {
            $out = [];
            foreach ($files as $key => $value) {
                if (is_array($value)) {
                    $out = array_merge($out, $flatten($value));
                } else {
                    $out[] = $value; // UploadedFile instance
                }
            }

            return $out;
        };

        // 2) Sources prioritaires connues: images (array ou single), image (single), photos (array ou single), files (array)
        $candidates = [];

        if ($request->hasFile('images')) {
            $candidates[] = $request->file('images');
        }
        if ($request->hasFile('image')) {
            $candidates[] = $request->file('image');
        }
        if ($request->hasFile('photos')) {
            $candidates[] = $request->file('photos');
        }
        if ($request->hasFile('files')) {
            $candidates[] = $request->file('files');
        }

        // Ajouter toute autre clé fichier détectée (fallback)
        foreach ($allFiles as $key => $val) {
            $candidates[] = $val;
        }

        // Aplatir en une liste d'UploadedFile
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }
            if (is_array($candidate)) {
                $images = array_merge($images, $flatten($candidate));
            } else {
                $images[] = $candidate;
            }
        }

        // Filtrer les images nulles ou invalides
        $images = array_values(array_filter($images, function ($image) {
            return $image !== null && method_exists($image, 'isValid') && $image->isValid();
        }));

        Log::info('Valid images found after flattening: '.count($images));

        if (empty($images)) {
            Log::warning('No valid images found in the request');

            return 0;
        }

        // Limiter le nombre d'images
        if (count($images) > 10) {
            throw new Exception('Maximum 10 images allowed per ad.');
        }

        $directory = 'ads/'.$ad->id;

        foreach ($images as $index => $image) {
            try {
                $originalName = method_exists($image, 'getClientOriginalName') ? $image->getClientOriginalName() : 'unknown';
                Log::info("Processing image {$index}: ".$originalName);

                // Valider le type MIME
                $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $mime = method_exists($image, 'getMimeType') ? $image->getMimeType() : null;
                if ($mime === null || ! in_array($mime, $allowedMimes)) {
                    Log::warning("Image {$index} has invalid or unknown MIME type: ".$mime);

                    continue;
                }

                // Valider la taille (max 5MB)
                $size = method_exists($image, 'getSize') ? $image->getSize() : 0;
                if ($size > 5 * 1024 * 1024) {
                    Log::warning("Image {$index} is too large: ".$size.' bytes');

                    continue;
                }

                // Générer un nom de fichier unique
                $extension = method_exists($image, 'getClientOriginalExtension') ? $image->getClientOriginalExtension() : 'jpg';
                $fileName = sprintf(
                    '%s_%s_%d.%s',
                    $ad->id,
                    time(),
                    $index,
                    $extension
                );

                // Enregistrer sur le disque public
                $stored = Storage::disk('public')->putFileAs($directory, $image, $fileName);
                if (! $stored) {
                    Log::warning('Failed to store image on disk for index '.$index);

                    continue;
                }

                // Créer l'enregistrement AdImage avec le chemin
                AdImage::create([
                    'ad_id' => $ad->id,
                    'image_path' => $directory.'/'.$fileName,
                    'is_primary' => $imagesProcessed === 0, // La première image valide est primaire.
                ]);

                $imagesProcessed++;
            } catch (Exception $e) {
                Log::error("Error processing image {$index}: ".$e->getMessage());

                continue;
            }
        }

        Log::info("Total images processed successfully: {$imagesProcessed}");

        return $imagesProcessed;
    }

    /**
     * Afficher une annonce spécifique.
     *
     * @OA\Get(
     *     path="/api/v1/ads/{id}",
     *     summary="Obtenir une annonce spécifique",
     *     description="Récupérer les informations détaillées d'une annonce spécifique incluant ses images et relations (utilisateur, quartier, ville, type de bien)",
     *     operationId="obtenirAnnonce",
     *     tags={"Annonces"},
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
        $ad = Ad::with(['images', 'user', 'ad_type', 'quarter.city'])
            ->findOrFail($id);

        $this->authorize('view', $ad);

        return response()->json([
            'success' => true,
            'data' => new AdResource($ad),
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
     *     tags={"Annonces"},
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
     *                     description="Nouvelles images à ajouter (maximum 10 images au total par annonce, 5MB chacune)",
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

            // Mettre à jour l'annonce
            $ad->update($data);

            Log::info('Ad updated with ID: '.$ad->id);

            // Gérer les nouvelles images si présentes
            $imagesProcessed = 0;
            if (! empty($request->allFiles())) {
                $imagesProcessed = $this->handleImageUpload($request, $ad);
            }

            // Gérer la suppression d'images existantes si demandée
            if ($request->has('images_to_delete') && is_array($request->images_to_delete)) {
                $this->handleImageDeletion($request->images_to_delete, $ad);
            }

            Log::info('New images processed: '.$imagesProcessed);

            DB::commit();

            // Recharger les relations
            $ad->load([
                'images',
                'user',
                'ad_type',
                'quarter.city',
            ]);

            $actualImagesCount = $ad->images()->count();

            return response()->json([
                'success' => true,
                'message' => 'Ad updated successfully',
                'data' => [
                    'ad' => new AdResource($ad),
                    'images_count' => $actualImagesCount,
                    'new_images_processed' => $imagesProcessed,
                ],
            ]);

        } catch (Throwable $e) {
            DB::rollback();

            Log::error('Error updating ad: '.$e->getMessage(), [
                'ad_id' => $ad->id,
                'user_id' => auth()->id(),
                'data' => $data,
                'files' => $request->allFiles(),
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
     * Gérer la suppression d'images spécifiques
     */
    private function handleImageDeletion(array $imageIds, Ad $ad): void
    {
        Log::info('Starting deletion of specific images', ['image_ids' => $imageIds]);

        foreach ($imageIds as $imageId) {
            try {
                $adImage = AdImage::where('id', $imageId)
                    ->where('ad_id', $ad->id)
                    ->first();

                if ($adImage) {
                    // Supprimer le fichier du disque si présent
                    if (! empty($adImage->image_path)) {
                        Storage::disk('public')->delete($adImage->image_path);
                    }
                    $adImage->delete();

                    Log::info('Image deleted successfully with ID: '.$imageId);
                } else {
                    Log::warning('Image not found or does not belong to ad: '.$imageId);
                }
            } catch (Exception $e) {
                Log::error('Error deleting image ID '.$imageId.': '.$e->getMessage());

                // Continue avec les autres images même si une échoue
                continue;
            }
        }

        // Réorganiser les images primaires si nécessaire
        $this->ensurePrimaryImage($ad);
    }

    /**
     * S'assurer qu'il y a une image primaire
     */
    private function ensurePrimaryImage(Ad $ad): void
    {
        $hasPrimary = $ad->images()->where('is_primary', true)->exists();

        if (! $hasPrimary) {
            $firstImage = $ad->images()->first();
            if ($firstImage) {
                $firstImage->update(['is_primary' => true]);
                Log::info('Set new primary image for ad ID: '.$ad->id.', image ID: '.$firstImage->id);
            }
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
     *     tags={"Annonces"},
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
     */
    public function destroy(string $id): JsonResponse
    {
        $ad = Ad::with(['images'])->findOrFail($id);

        $this->authorize('delete', $ad);

        DB::beginTransaction();

        try {
            Log::info('Starting deletion of ad with ID: '.$id);

            // Supprimer toutes les images associées
            $imagesCount = $ad->images()->count();
            foreach ($ad->images as $adImage) {
                // Supprimer le fichier du disque
                if (! empty($adImage->image_path)) {
                    Storage::disk('public')->delete($adImage->image_path);
                }
                $adImage->delete();
            }

            // Supprimer le dossier de l'annonce s'il est vide
            $dir = 'ads/'.$ad->id;
            try {
                $filesLeft = Storage::disk('public')->files($dir);
                if (empty($filesLeft)) {
                    Storage::disk('public')->deleteDirectory($dir);
                }
            } catch (\Throwable $e) {
                Log::warning('Could not cleanup directory: '.$dir.' due to: '.$e->getMessage());
            }

            Log::info('Deleted '.$imagesCount.' images for ad ID: '.$id);

            // Supprimer l'annonce
            $ad->delete();

            Log::info('Ad deleted successfully with ID: '.$id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ad deleted successfully',
                'data' => [
                    'deleted_ad_id' => $id,
                    'deleted_images_count' => $imagesCount,
                ],
            ], 200);

        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error deleting ad: '.$e->getMessage(), [
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
