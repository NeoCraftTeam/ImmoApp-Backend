<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Throwable;

class AuthController
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/registerCustomer",
     *     tags={"🔐 Authentification"},
     *     summary="Inscription d'un nouveau client",
     *     description="Permet l'inscription d'un nouvel utilisateur avec validation des données, gestion d'avatar optionnel et localisation GPS optionnelle",
     *     operationId="registerCustomer",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données d'inscription de l'utilisateur",
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"firstname", "lastname", "email", "phone_number", "password", "confirm_password", "city_id"},
     *
     *                 @OA\Property(
     *                     property="firstname",
     *                     type="string",
     *                     maxLength=50,
     *                     example="Jean",
     *                     description="Prénom de l'utilisateur"
     *                 ),
     *                 @OA\Property(
     *                     property="lastname",
     *                     type="string",
     *                     maxLength=50,
     *                     example="Dupont",
     *                     description="Nom de famille de l'utilisateur"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     example="jean.dupont@example.com",
     *                     description="Adresse email unique de l'utilisateur"
     *                 ),
     *                 @OA\Property(
     *                     property="phone_number",
     *                     type="string",
     *                     example="+33123456789",
     *                     description="Numéro de téléphone avec indicatif pays"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string",
     *                     format="password",
     *                     minLength=8,
     *                     example="Motdepasse123%",
     *                     description="Mot de passe (minimum 8 caractères)"
     *                 ),
     *                 @OA\Property(
     *                     property="confirm_password",
     *                     type="string",
     *                     format="password",
     *                     minLength=8,
     *                     example="Motdepasse123%",
     *                     description="Confirmation du mot de passe (doit correspondre au password)"
     *                 ),
     *                 @OA\Property(
     *                     property="role",
     *                     type="string",
     *                     enum={"customer"},
     *                     default="customer",
     *                     description="Rôle de l'utilisateur (automatiquement défini à 'customer')"
     *                 ),
     *                 @OA\Property(
     *                     property="city_id",
     *                     type="integer",
     *                     example=1,
     *                     description="ID de la ville de résidence de l'utilisateur"
     *                 ),
     *                 @OA\Property(
     *                     property="latitude",
     *                     type="number",
     *                     format="float",
     *                     minimum=-90,
     *                     maximum=90,
     *                     nullable=true,
     *                     example=48.8566,
     *                     description="Latitude GPS de la position de l'utilisateur (optionnel, doit être fournie avec longitude)"
     *                 ),
     *                 @OA\Property(
     *                     property="longitude",
     *                     type="number",
     *                     format="float",
     *                     minimum=-180,
     *                     maximum=180,
     *                     nullable=true,
     *                     example=2.3522,
     *                     description="Longitude GPS de la position de l'utilisateur (optionnel, doit être fournie avec latitude)"
     *                 ),
     *                 @OA\Property(
     *                     property="avatar",
     *                     type="string",
     *                     format="binary",
     *                     nullable=true,
     *                     description="Image d'avatar (optionnel, formats acceptés: JPEG, PNG, GIF, WebP, max 2MB)"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Inscription réussie",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Inscription réussie."),
     *             @OA\Property(property="access_token", type="string", example="1|abc123def456...", description="Token d'authentification Bearer"),
     *             @OA\Property(property="email_verification_required", type="boolean", example=true, description="Indique si la vérification email est requise")
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
     *         response=413,
     *         description="Fichier avatar trop volumineux",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Le fichier avatar est trop volumineux."),
     *             @OA\Property(property="max_size", type="string", example="2MB")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation des données fournies",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Erreur de validation."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Détails des erreurs de validation par champ",
     *                 example={
     *                     "email": {"Le champ email doit être une adresse email valide."},
     *                     "latitude": {"The latitude must be between -90 and 90."},
     *                     "longitude": {"The longitude field is required when latitude is present."},
     *                     "confirm_password": {"The confirm password and password must match."}
     *                 }
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Limitation de débit - Trop de tentatives d'inscription",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Trop de tentatives d'inscription. Réessayez dans 300 secondes."),
     *             @OA\Property(property="retry_after", type="integer", example=300, description="Nombre de secondes à attendre avant de réessayer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue lors de l'inscription. Veuillez réessayer."),
     *             @OA\Property(property="error", type="string", nullable=true, example="Database connection failed", description="Détails de l'erreur (uniquement en mode debug)")
     *         )
     *     )
     * )
     */
    public function registerCustomer(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['role'] = 'customer';

        // Appel de la méthode privée
        return $this->registerUser($data, $request);
    }

    private function registerUser(array $data, RegisterRequest $request): JsonResponse
    {
        try {
            // Vérifier le rate limiting personnalisé
            $key = 'register-attempts:'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $seconds = RateLimiter::availableIn($key);

                Log::warning('Too many registration attempts', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'message' => 'Trop de tentatives d\'inscription. Réessayez dans '.$seconds.' secondes.',
                    'retry_after' => $seconds,
                ], 429);
            }

            // Fusionner les données validées avec les données supplémentaires
            $data = array_merge($request->validated(), $data);

            // Vérifier si l'utilisateur existe déjà (double sécurité)
            if (User::where('email', $data['email'])->exists()) {
                RateLimiter::hit($key, 600); // 10 minutes de blocage

                Log::warning('Registration attempt with existing email', [
                    'email' => $data['email'],
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Cette adresse email est déjà utilisée.',
                ], 409);
            }

            // Transaction pour assurer la cohérence
            $result = DB::transaction(function () use ($request, $data) {

                // Créer l'utilisateur
                $user = User::create([
                    'firstname' => $data['firstname'],
                    'lastname' => $data['lastname'],
                    'email' => $data['email'],
                    'phone_number' => $data['phone_number'],
                    'password' => Hash::make($data['password']),
                    'location' => (isset($data['latitude'], $data['longitude']) && $data['latitude'] !== null && $data['longitude'] !== null)
                        ? Point::makeGeodetic((float) $data['latitude'], (float) $data['longitude'])
                        : null,
                    'role' => $data['role'] ?? 'admin', // Valeur par défaut
                    'type' => $data['type'] ?? 'individual',
                    'city_id' => $data['city_id'] ?? null,
                    'is_active' => true,
                    'email_verified_at' => null, // Forcer la vérification email
                    'last_login_ip' => $request->ip(),
                    'created_at' => now(),
                ]);

                // Gestion de l'avatar avec validation approfondie
                if ($request->hasFile('avatar')) {
                    $user->clearMediaCollection('avatars');
                    $user->addMediaFromRequest('avatar')
                        ->usingName($user->firstname.'_'.$user->lastname.'_avatar')
                        ->toMediaCollection('avatars');
                }

                // Créer le token d'accès
                $token = $user->createToken(
                    'registration_token_'.now()->timestamp,
                );

                return ['user' => $user, 'token' => $token];

            });

            $user = $result['user'];
            $token = $result['token'];

            // Déclencher l'événement d'inscription (envoie l'email automatiquement)
            event(new Registered($user));

            // Réinitialiser les tentatives d'inscription échouées
            RateLimiter::clear($key);

            // Log de succès
            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Inscription réussie.',
                'user' => new UserResource($user),
                'access_token' => $token->plainTextToken,
                'email_verification_required' => $user->email_verified_at === null,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation.',
                'errors' => $e->errors(),
            ], 422);

        } catch (FileIsTooBig $e) {
            Log::warning('File too big during registration', [
                'error' => $e->getMessage(),
                'request_data' => $request->except(['password', 'avatar']),
            ]);

            return response()->json([
                'message' => 'Le fichier avatar est trop volumineux.',
                'max_size' => '2MB',
            ], 413);

        } catch (FileDoesNotExist $e) {
            Log::warning('File does not exist during registration', [
                'error' => $e->getMessage(),
                'request_data' => $request->except(['password', 'avatar']),
            ]);

            return response()->json([
                'message' => 'Le fichier avatar est introuvable.',
            ], 400);

        } catch (Throwable $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password', 'avatar']),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/registerAgent",
     *     tags={"🔐 Authentification"},
     *     summary="Inscription d'un nouvel agent",
     *     description="Permet l'inscription d'un nouvel utilisateur avec validation des données et gestion d'avatar optionnel",
     *     operationId="registerAgent",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données d'inscription de l'utilisateur",
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"firstname", "lastname", "email", "phone_number", "password"},
     *
     *                 @OA\Property(
     *                     property="firstname",
     *                     type="string",
     *                     maxLength=50,
     *                     example="Jean",
     *                     description="Prénom de l'utilisateur"
     *                 ),
     *                 @OA\Property(
     *                     property="lastname",
     *                     type="string",
     *                     maxLength=50,
     *                     example="Dupont",
     *                     description="Nom de famille de l'utilisateur"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     example="jean.dupont@example.com",
     *                     description="Adresse email unique de l'utilisateur"
     *                 ),
     *                 @OA\Property(
     *                     property="phone_number",
     *                     type="string",
     *                     example="+33123456789",
     *                     description="Numéro de téléphone de l'utilisateur"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string",
     *                     format="password",
     *                     minLength=8,
     *                     example="Motdepasse123%",
     *                     description="Mot de passe (minimum 8 caractères)"
     *                 ),
     *                  @OA\Property(
     *                      property="confirm_password",
     *                      type="string",
     *                      format="password",
     *                      minLength=8,
     *                      example="Motdepasse123%",
     *                      description="Mot de passe de confirmation (minimum 8 caractères)"
     *                  ),
     *                 @OA\Property(
     *                     property="role",
     *                     type="string",
     *                     enum={"agent"},
     *                     default="agent",
     *                     example="user",
     *                     description="Rôle de l'utilisateur"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"agency", "individual"},
     *                     example="premium",
     *                     nullable=false,
     *                     description="Type d'utilisateur (agence ou individuel)"
     *                 ),
     *                 @OA\Property(
     *                     property="city_id",
     *                     type="integer",
     *                     nullable=false,
     *                     example=1,
     *                     description="ID de la ville de résidence"
     *                 ),
     *                 @OA\Property(
     *                     property="avatar",
     *                     type="string",
     *                     format="binary",
     *                     description="Image d'avatar (optionnel, max 2MB)"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Inscription réussie",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Inscription réussie."),
     *             @OA\Property(property="access_token", type="string", example="1|abc123def456..."),
     *             @OA\Property(property="email_verification_required", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Email déjà utilisé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Cette adresse email est déjà utilisée.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=413,
     *         description="Fichier avatar trop volumineux",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Le fichier avatar est trop volumineux."),
     *             @OA\Property(property="max_size", type="string", example="2MB")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Erreur de validation."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={"email": {"Le champ email doit être une adresse email valide."}}
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Trop de tentatives d'inscription",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Trop de tentatives d'inscription. Réessayez dans 300 secondes."),
     *             @OA\Property(property="retry_after", type="integer", example=300)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue lors de l'inscription. Veuillez réessayer."),
     *             @OA\Property(property="error", type="string", example="Database connection failed")
     *         )
     *     )
     * )
     */
    public function registerAgent(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['role'] = 'agent';

        // Appel de la méthode privée
        return $this->registerUser($data, $request);
    }

    public function registerAdmin(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['role'] = 'admin';

        // Appel de la méthode privée
        return $this->registerUser($data, $request);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/verifyEmail",
     *     tags={"🔐 Authentification"},
     *     summary="Vérification de l'adresse email",
     *     description="Vérifie l'adresse email de l'utilisateur via un lien de vérification",
     *     operationId="verifyEmail",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"id", "hash"},
     *
     *             @OA\Property(property="id", type="integer", example=1, description="ID de l'utilisateur"),
     *             @OA\Property(property="hash", type="string", example="abc123def456", description="Hash de vérification")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email vérifié avec succès ou déjà vérifié",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Email vérifié avec succès.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Lien de vérification invalide",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Lien de vérification invalide.")
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
     *     )
     * )
     */
    public function verifyEmail($id, $hash, Request $request)
    {
        Log::info('VerifyEmail called with ID: '.$id);

        if (! Str::isUuid($id)) {
            Log::warning('Invalid UUID provided: '.$id);
            if ($request->wantsJson()) {
                return response()->json(['message' => 'ID utilisateur invalide.'], 400);
            }

            return abort(400, 'ID utilisateur invalide.');
        }

        try {
            $user = User::findOrFail($id);

            // Vérifier le hash
            if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'Lien de vérification invalide'], 400);
                }

                return abort(400, 'Lien de vérification invalide.');
            }

            if ($user->hasVerifiedEmail()) {
                return $request->wantsJson()
                    ? response()->json(['message' => 'Email déjà vérifié.', 'verified' => true])
                    : view('auth.verified');
            }

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));

                // Log de succès
                Log::info('Email verified successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Email vérifié avec succès.', 'verified' => true])
                : view('auth.verified');

        } catch (ModelNotFoundException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Utilisateur non trouvé.'], 404);
            }

            return abort(404, 'Utilisateur non trouvé.');

        } catch (Throwable $e) {
            Log::error('Email verification failed', [
                'error' => $e->getMessage(),
                'user_id' => $id,
            ]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Erreur lors de la vérification.'], 500);
            }

            return abort(500, 'Erreur lors de la vérification.');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/resendVerificationEmail",
     *     tags={"🔐 Authentification"},
     *     summary="Renvoyer l'email de vérification",
     *     description="Renvoie un email de vérification à l'utilisateur",
     *     operationId="resendVerificationEmail",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="jean.dupont@example.com", description="Adresse email de l'utilisateur")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email de vérification renvoyé ou déjà vérifié",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Email de vérification renvoyé.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Utilisateur non trouvé.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Trop de demandes de renvoi",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Trop de demandes. Réessayez dans 300 secondes.")
     *         )
     *     )
     * )
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email déjà vérifié.',
            ]);
        }

        // Rate limiting pour éviter le spam
        $key = 'resend-verification:'.$request->ip().':'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 2)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Trop de demandes. Réessayez dans '.$seconds.' secondes.',
            ], 429);
        }

        $user->sendEmailVerificationNotification();
        RateLimiter::hit($key, 300); // 5 minutes

        return response()->json([
            'message' => 'Email de vérification renvoyé.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"🔐 Authentification"},
     *     summary="Connexion utilisateur",
     *     description="Authentifie un utilisateur et retourne un token d'accès",
     *     operationId="login",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="jean.dupont@example.com", description="Adresse email de l'utilisateur"),
     *             @OA\Property(property="password", type="string", format="password", example="motdepasse123", description="Mot de passe de l'utilisateur")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Connexion réussie."),
     *             @OA\Property(property="access_token", type="string", example="1|abc123def456..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Identifiants invalides",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Identifiants invalides.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Compte désactivé ou email non vérifié",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Compte désactivé. Contactez l'administrateur.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Données de validation invalides."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={"email": {"Le champ email doit être une adresse email valide."}}
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Trop de tentatives de connexion",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Trop de tentatives de connexion. Réessayez dans 300 secondes."),
     *             @OA\Property(property="retry_after", type="integer", example=300)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue lors de la connexion.")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();
            $email = $credentials['email'];
            $password = $credentials['password'];

            // Vérifier le rate limiting personnalisé
            $key = 'login-attempts:'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);

                Log::warning('Too many login attempts', [
                    'ip' => $request->ip(),
                    'email' => $email,
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'message' => 'Trop de tentatives de connexion. Réessayez dans '.$seconds.' secondes.',
                    'retry_after' => $seconds,
                ], 429);
            }

            // Récupérer l'utilisateur
            $user = User::where('email', $email)->first();

            // Vérification des credentials avec timing attack protection
            if (! $user || ! Hash::check($password, $user->password)) {
                // Incrémenter les tentatives échouées
                RateLimiter::hit($key, 300); // 5 minutes de blocage

                // Log de sécurité
                Log::warning('Failed login attempt', [
                    'email' => $email,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now(),
                ]);

                return response()->json([
                    'message' => 'Identifiants invalides.',
                ], 401);
            }

            // Vérifier si le compte est actif
            if (isset($user->is_active) && ! $user->is_active) {
                Log::info('Login attempt on inactive account', [
                    'user_id' => $user->id,
                    'email' => $email,
                ]);

                return response()->json([
                    'message' => 'Compte désactivé. Contactez l\'administrateur.',
                ], 403);
            }

            // Vérifier si l'email est vérifié (optionnel)
            if ($user->email_verified_at === null) {
                return response()->json([
                    'message' => 'Veuillez vérifier votre adresse email avant de vous connecter.',
                ], 403);
            }

            // Réinitialiser les tentatives échouées
            RateLimiter::clear($key);

            // Créer le token avec expiration
            $tokenName = 'api_token_'.now()->timestamp;

            $token = $user->createToken(
                $tokenName,
                ['*'], // abilities (permissions du token)
                now()->addDays(7) // expiration dans 7 jours
            );

            // Mettre à jour les informations de connexion
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            // Log de connexion réussie
            Log::info('Successful login', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Connexion réussie.',
                'access_token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Données de validation invalides.',
                'errors' => $e->errors(),
            ], 422);

        } catch (Throwable $e) {
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password']),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la connexion.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"🔐 Authentification"},
     *     summary="Déconnexion utilisateur",
     *     description="Déconnecte l'utilisateur en révoquant son token d'accès",
     *     operationId="logout",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *          name="Authorization",
     *          in="header",
     *          required=true,
     *          description="Bearer token actuel à rafraîchir",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="Bearer 1|abc123def456ghi789jkl012mno345pqr678stu901vwx234yz"
     *          )
     *      ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Déconnexion réussie",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Déconnexion réussie.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Token d'authentification manquant ou invalide",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Erreur lors de la déconnexion.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $token = $request->user()->currentAccessToken();

            // Log de déconnexion
            Log::info('User logout', [
                'user_id' => $user->id,
                'token_name' => $token->name,
            ]);

            // Supprimer le token actuel
            $token->delete();

            return response()->json([
                'message' => 'Déconnexion réussie.',
            ], 200);

        } catch (Throwable $e) {
            Log::error('Logout error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'message' => 'Erreur lors de la déconnexion.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     tags={"🔐 Authentification"},
     *     summary="Informations de l'utilisateur connecté",
     *     description="Retourne les informations de l'utilisateur actuellement authentifié",
     *     operationId="me",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *          name="Authorization",
     *          in="header",
     *          required=true,
     *          description="Bearer token pour l'authentification",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="Bearer 1|abc123def456ghi789jkl012mno345pqr678stu901vwx234yz"
     *          )
     *      ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Informations utilisateur récupérées avec succès",
     *
     *     @OA\JsonContent(
     *              type="object",
     *
     *              @OA\Property(property="message", type="string", example="authenticated.")
     *          )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Token d'authentification manquant ou invalide",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/refresh",
     *     tags={"🔐 Authentification"},
     *     summary="Rafraîchir le token d'accès",
     *     description="Génère un nouveau token d'accès et révoque l'ancien",
     *     operationId="refresh",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *          name="Authorization",
     *          in="header",
     *          required=true,
     *          description="Bearer token actuel à rafraîchir",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="Bearer 1|abc123def456ghi789jkl012mno345pqr678stu901vwx234yz"
     *          )
     *      ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Token rafraîchi avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string", example="2|def789ghi012..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_at", type="string", format="date-time")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Token d'authentification manquant ou invalide",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Erreur lors du rafraîchissement du token.")
     *         )
     *     )
     * )
     */
    public function refresh(Request $request)
    {
        try {
            $user = $request->user();
            $currentToken = $request->user()->currentAccessToken();

            // Créer un nouveau token
            $newToken = $user->createToken(
                'refreshed_token_'.now()->timestamp,
                $currentToken->abilities,
                now()->addDays(7)
            );

            // Supprimer l'ancien token
            $currentToken->delete();

            return response()->json([
                'access_token' => $newToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $newToken->accessToken->expires_at,
            ], 200);

        } catch (Throwable $e) {
            Log::error('Token refresh error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'message' => 'Erreur lors du rafraîchissement du token.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/forgot-password",
     *     tags={"🔐 Authentification"},
     *     summary="Mot de passe oublié",
     *     description="Envoie un lien de réinitialisation de mot de passe par email",
     *     operationId="forgotPassword",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Lien envoyé",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Nous vous avons envoyé par email le lien de réinitialisation du mot de passe !"))
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Impossible de trouver un utilisateur avec cette adresse email."))
     *     )
     * )
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/reset-password",
     *     tags={"🔐 Authentification"},
     *     summary="Réinitialiser le mot de passe",
     *     description="Définit un nouveau mot de passe en utilisant le token reçu par email",
     *     operationId="resetPassword",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"token", "email", "password", "password_confirmation"},
     *
     *             @OA\Property(property="token", type="string", description="Le token reçu dans l'email"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="NewPassword123!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="NewPassword123!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Mot de passe réinitialisé",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Votre mot de passe a été réinitialisé !"))
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur (token invalide, email incorrect...)",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Ce jeton de réinitialisation de mot de passe est invalide."))
     *     )
     * )
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new \Illuminate\Auth\Events\PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/update-password",
     *     tags={"🔐 Authentification"},
     *     summary="Mettre à jour le mot de passe (connecté)",
     *     description="Permet à un utilisateur connecté de changer son mot de passe actuel",
     *     operationId="updatePassword",
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"current_password", "new_password", "new_password_confirmation"},
     *
     *             @OA\Property(property="current_password", type="string", format="password", example="OldPass123!"),
     *             @OA\Property(property="new_password", type="string", format="password", example="NewPass456!"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="NewPass456!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Mot de passe mis à jour",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Mot de passe mis à jour avec succès."))
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation (ancien mot de passe incorrect...)",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Le mot de passe actuel est incorrect."))
     *     )
     * )
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|confirmed|min:8|different:current_password',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Le mot de passe actuel est incorrect.'], 422);
        }

        $user->fill([
            'password' => Hash::make($request->new_password),
        ])->save();

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }
}
