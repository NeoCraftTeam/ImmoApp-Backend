<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserController
{
    use AuthorizesRequests;

    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     operationId="getUsersList",
     *     security={{"bearerAuth":{}}},
     *     tags={"👤 Utilisateur"},
     *     summary="Liste des utilisateurs",
     *     description="Récupère la liste paginée des utilisateurs avec leurs relations (ville et média)",
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page pour la pagination",
     *         required=false,
     *
     *         @OA\Schema(
     *             type="integer",
     *             minimum=1,
     *             default=1
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *
     *         @OA\Schema(
     *             type="integer",
     *             minimum=1,
     *             maximum=100,
     *             default=10
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste des utilisateurs récupérée avec succès",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/User")
     *             ),
     *
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 @OA\Property(property="first", type="string", example="http://example.com/api/v1/users?page=1"),
     *                 @OA\Property(property="last", type="string", example="http://example.com/api/v1/users?page=10"),
     *                 @OA\Property(property="prev", type="string", nullable=true),
     *                 @OA\Property(property="next", type="string", nullable=true)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé - Token manquant ou invalide",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Accès interdit - Permissions insuffisantes",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="This action is unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Erreur du serveur")
     *         )
     *     )
     * )
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('city')->paginate(config('pagination.default', 10));

        return UserResource::collection($users);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users",
     *     operationId="createUser",
     *     security={{"bearerAuth":{}}},
     *     tags={"👤 Utilisateur"},
     *     summary="Créer un nouvel utilisateur",
     *     description="Crée un nouvel utilisateur avec avatar et localisation GPS optionnels et génère un token d'accès",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données de l'utilisateur à créer",
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"firstname", "lastname", "email", "password", "confirm_password", "phone_number", "role", "city_id"},
     *
     *                 @OA\Property(property="firstname", type="string", maxLength=255, example="Jean", description="Prénom de l'utilisateur"),
     *                 @OA\Property(property="lastname", type="string", maxLength=255, example="Dupont", description="Nom de famille de l'utilisateur"),
     *                 @OA\Property(property="email", type="string", format="email", example="jean.dupont@example.com", description="Adresse email unique"),
     *                 @OA\Property(property="password", type="string", format="password", minLength=8, example="motdepasse123", description="Mot de passe (min. 8 caractères)"),
     *                 @OA\Property(property="confirm_password", type="string", format="password", minLength=8, example="motdepasse123", description="Confirmation du mot de passe (doit correspondre au password)"),
     *                 @OA\Property(property="phone_number", type="string", example="+33123456789", description="Numéro de téléphone avec indicatif pays"),
     *                 @OA\Property(property="role", type="string", enum={"customer", "agent"}, example="agent", description="Rôle de l'utilisateur dans le système"),
     *                 @OA\Property(property="type", type="string", enum={"individual", "agency"}, nullable=true, example="individual", description="Type d'agent (requis uniquement si role=agent)"),
     *                 @OA\Property(property="city_id", type="integer", example=1, description="Identifiant de la ville de l'utilisateur"),
     *                 @OA\Property(property="latitude", type="number", format="float", minimum=-90, maximum=90, nullable=true, example=48.8566, description="Latitude GPS de la localisation de l'utilisateur (optionnel)"),
     *                 @OA\Property(property="longitude", type="number", format="float", minimum=-180, maximum=180, nullable=true, example=2.3522, description="Longitude GPS de la localisation de l'utilisateur (optionnel)"),
     *                 @OA\Property(property="avatar", type="string", format="binary", nullable=true, description="Photo de profil de l'utilisateur (formats acceptés: JPEG, PNG, GIF, WebP)")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Utilisateur créé avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Création réussie."),
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="access_token", type="string", example="1|abcdef123456...", description="Token d'authentification Bearer pour les futures requêtes")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Données de validation invalides",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Détails des erreurs de validation par champ",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The email field is required.")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="latitude",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The latitude must be between -90 and 90.")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="confirm_password",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The confirm password and password must match.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé - Token d'authentification manquant ou invalide",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Accès interdit - Permissions insuffisantes pour créer un utilisateur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="This action is unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Conflit - Email déjà utilisé par un autre compte",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Cette adresse email est déjà utilisée.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation - Latitude et longitude doivent être fournies ensemble",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="longitude",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The longitude field is required when latitude is present.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur - Échec de création",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Impossible de créer l'utilisateur pour le moment."),
     *             @OA\Property(property="error", type="string", nullable=true, example="Database connection failed", description="Détails de l'erreur (uniquement en mode debug)")
     *         )
     *     )
     * )
     */
    public function store(UserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        // Vérification email existant avant transaction
        if (User::where('email', $data['email'])->exists()) {
            Log::warning('User creation attempt with existing email', [
                'email' => $data['email'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Cette adresse email est déjà utilisée.',
            ], 409);
        }

        try {
            DB::beginTransaction();

            // Création de l'utilisateur
            $user = User::create([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone_number' => $data['phone_number'],
                'role' => $data['role'],
                'location' => (isset($data['latitude'], $data['longitude']) && $data['latitude'] !== null && $data['longitude'] !== null)
                    ? Point::makeGeodetic((float) $data['latitude'], (float) $data['longitude'])
                    : null,
                'type' => $data['type'] ?? null,
                'city_id' => $data['city_id'],
                'is_active' => true,
            ]);

            // Gestion de l'avatar (le modèle gère le default)
            if ($request->hasFile('avatar')) {
                $user->clearMediaCollection('avatars');
                $user->addMediaFromRequest('avatar')
                    ->usingName($user->firstname.'_'.$user->lastname.'_avatar')
                    ->toMediaCollection('avatars');
            }

            // Création du token
            $token = $user->createToken('creation_token_'.now()->timestamp);

            DB::commit();

            return response()->json([
                'message' => 'Création réussie.',
                'user' => new UserResource($user),
                'access_token' => $token->plainTextToken,
            ], 201);

        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('User creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Impossible de créer l’utilisateur pour le moment.',
                'error' => $e->getMessage(), // tu peux supprimer si tu ne veux pas exposer l'erreur
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     operationId="getUserById",
     *     security={{"bearerAuth":{}}},
     *     tags={"👤 Utilisateur"},
     *     summary="Afficher un utilisateur spécifique",
     *     description="Récupère les détails d'un utilisateur par son ID avec ses relations",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID unique de l'utilisateur",
     *
     *         @OA\Schema(
     *             type="integer",
     *             format="int64",
     *             minimum=1,
     *             example=1
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'utilisateur récupérés avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Accès interdit - Permissions insuffisantes",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="This action is unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Utilisateur non trouvé")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Erreur du serveur")
     *         )
     *     )
     * )
     */
    public function show(string $id): UserResource|JsonResponse
    {
        $this->authorize('view', User::class);
        $userId = User::find($id);
        if (! $userId) {
            return response()->json([
                'message' => 'Utilisateur non trouvé',
            ], 404);
        }

        return new UserResource($userId);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/users/{user}",
     *     operationId="updateUser",
     *     security={{"bearerAuth":{}}},
     *     tags={"👤 Utilisateur"},
     *     summary="Mettre à jour un utilisateur",
     *     description="Met à jour les informations d'un utilisateur existant avec gestion d'avatar",
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="ID de l'utilisateur à mettre à jour",
     *
     *         @OA\Schema(
     *             type="integer",
     *             format="int64",
     *             minimum=1,
     *             example=1
     *         )
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *         description="Données de l'utilisateur à mettre à jour (tous les champs sont optionnels)",
     *
     *         @OA\MediaType(
     *            mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(property="firstname", type="string", maxLength=255),
     *                 @OA\Property(property="lastname", type="string", maxLength=255),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="password", type="string", format="password", minLength=8),
     *                 @OA\Property(property="phone_number", type="string"),
     *                 @OA\Property(property="city_id", type="integer"),
     *                 @OA\Property(property="avatar", type="string", format="binary", description="Nouvel avatar de l'utilisateur (image)")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur mis à jour avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Utilisateur mis à jour avec succès."),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Données de validation invalides",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The email format is invalid.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Accès interdit - Permissions insuffisantes",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="This action is unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\User]")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Conflit - Email déjà utilisé par un autre utilisateur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Cette adresse email est déjà utilisée.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Impossible de mettre à jour l'utilisateur pour le moment."),
     *             @OA\Property(property="error", type="string", example="Database connection failed")
     *         )
     *     )
     * )
     */
    public function update(UserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $data = $request->validated();

        try {
            DB::beginTransaction();

            // Vérifier si l'email change et existe déjà
            if (isset($data['email']) && $data['email'] !== $user->email) {
                if (User::where('email', $data['email'])->exists()) {
                    Log::warning('User update attempt with existing email', [
                        'email' => $data['email'],
                        'user_id' => $user->id,
                        'ip' => $request->ip(),
                    ]);

                    return response()->json([
                        'message' => 'Cette adresse email est déjà utilisée.',
                    ], 409);
                }
            }

            // Mettre à jour les champs
            $user->fill($data);

            // Si le mot de passe est présent, le hacher
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            // Mettre à jour la localisation si fournie (lat/lng)
            if (array_key_exists('latitude', $data) || array_key_exists('longitude', $data)) {
                if (($data['latitude'] ?? null) !== null && ($data['longitude'] ?? null) !== null) {
                    $user->location = Point::makeGeodetic((float) $data['latitude'], (float) $data['longitude']);
                } else {
                    // Permettre de réinitialiser la localisation si null est envoyé
                    $user->location = null;
                }
            }

            $user->save();

            // Gestion de l'avatar (le modèle gère le default)
            if ($request->hasFile('avatar')) {
                $user->clearMediaCollection('avatars');
                $user->addMediaFromRequest('avatar')
                    ->usingName($user->firstname.'_'.$user->lastname.'_avatar')
                    ->toMediaCollection('avatars');
            }

            DB::commit();

            return response()->json([
                'message' => 'Utilisateur mis à jour avec succès.',
                'user' => new UserResource($user),
            ], 200);

        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('User update failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'data' => $data,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Impossible de mettre à jour l’utilisateur pour le moment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/{user}",
     *     operationId="deleteUser",
     *     security={{"bearerAuth":{}}},
     *     tags={"👤 Utilisateur"},
     *     summary="Supprimer un utilisateur",
     *     description="Supprime définitivement un utilisateur et toutes ses données associées (avatar, médias)",
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="ID de l'utilisateur à supprimer",
     *
     *         @OA\Schema(
     *             type="integer",
     *             format="int64",
     *             minimum=1,
     *             example=1
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur supprimé avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Utilisateur supprimé avec succès.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Accès interdit - Permissions insuffisantes",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="This action is unauthorized")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\User]")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Conflit - Impossible de supprimer l'utilisateur (contraintes de données)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Impossible de supprimer cet utilisateur car il est lié à d'autres données.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Impossible de supprimer l'utilisateur pour le moment."),
     *             @OA\Property(property="error", type="string", example="Database constraint violation")
     *         )
     *     )
     * )
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        try {
            DB::transaction(function () use ($user) {
                // Si tu gères des médias ou autres relations
                $user->clearMediaCollection('avatars');

                // Supprime l'utilisateur
                $user->delete();
            });

            Log::info('User deleted successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'deleted_at' => now(),
            ]);

            return response()->json([
                'message' => 'Utilisateur supprimé avec succès.',
            ], 200);
        } catch (Throwable $e) {
            Log::error('Failed to delete user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Impossible de supprimer l’utilisateur pour le moment.',
                'error' => $e->getMessage(), // optionnel, à cacher en prod
            ], 500);
        }
    }
}
