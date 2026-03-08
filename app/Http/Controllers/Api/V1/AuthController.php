<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Requests\Api\V1\ClerkExchangeRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\NewDeviceSignInMail;
use App\Mail\NewLocationSignInMail;
use App\Mail\PasswordChangedMail;
use App\Mail\VerificationCodeMail;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\ClerkJwtService;
use App\Services\UserAgentParser;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Throwable;

final class AuthController
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
        $data['type'] = 'individual'; // P1-2 Fix: Force type for customer registration

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
                    'message' => 'Les informations fournies sont invalides.',
                ], 422);
            }

            // Vérifier la prévention des multi-comptes par IP (hors localhost)
            $registrationIp = $request->ip();
            $isLocalhost = in_array($registrationIp, ['127.0.0.1', '::1'], true);
            if (!$isLocalhost) {
                $existingAccountsFromIp = User::where('registration_ip', $registrationIp)
                    ->where('created_at', '>=', now()->subDays((int) config('auth.ip_block_days', 30)))
                    ->count();

                if ($existingAccountsFromIp >= (int) config('auth.max_accounts_per_ip', 3)) {
                    Log::warning('Multi-account registration attempt blocked by IP', [
                        'ip' => $registrationIp,
                        'existing_accounts' => $existingAccountsFromIp,
                    ]);

                    return response()->json([
                        'message' => 'Vous ne pouvez pas créer plus de comptes depuis cette adresse IP.',
                    ], 422);
                }
            }

            // Transaction pour assurer la cohérence
            // P1-1 Fix: email uniqueness is also enforced by DB unique constraint;
            // catch UniqueConstraintViolationException for clean 409 response
            $result = DB::transaction(function () use ($request, $data) {

                // Créer l'utilisateur
                $user = new User;
                $user->fill([
                    'firstname' => $data['firstname'],
                    'lastname' => $data['lastname'],
                    'email' => $data['email'],
                    'phone_number' => $data['phone_number'],
                    'password' => $data['password'],
                    'location' => isset($data['latitude'], $data['longitude'])
                        ? Point::makeGeodetic((float) $data['latitude'], (float) $data['longitude'])
                        : null,
                    'type' => $data['type'] ?? 'individual',
                    'city_id' => $data['city_id'] ?? null,
                ]);
                $user->forceFill([
                    'role' => $data['role'],
                    'is_active' => true,
                    'email_verified_at' => null,
                    'last_login_ip' => $request->ip(),
                    'registration_ip' => $request->ip(),
                ]);
                $user->save();

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
                    ['*'],
                    now()->addDays(7)
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
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
                'request_data' => $request->except(['password', 'avatar']),
            ]);

            return response()->json([
                'message' => 'Le fichier avatar est trop volumineux.',
                'max_size' => '2MB',
            ], 413);

        } catch (FileDoesNotExist $e) {
            Log::warning('File does not exist during registration', [
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
                'request_data' => $request->except(['password', 'avatar']),
            ]);

            return response()->json([
                'message' => 'Le fichier avatar est introuvable.',
            ], 400);

            // P1-1 Fix: Catch DB unique constraint violation (concurrent signup race)
        } catch (UniqueConstraintViolationException) {
            Log::warning('Registration duplicate email (DB constraint)', [
                'email' => $data['email'] ?? 'unknown',
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Cette adresse email est déjà utilisée.',
            ], 409);

        } catch (Throwable $e) {
            Log::error('Registration failed', [
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password', 'avatar']),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
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

    /**
     * @OA\Post(
     *     path="/api/v1/auth/registerAdmin",
     *     summary="Enregistrer un administrateur",
     *     description="Crée un nouveau compte administrateur. Route protégée, accessible uniquement par un admin existant.",
     *     tags={"🔐 Authentification"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RegisterRequest")),
     *
     *     @OA\Response(response=201, description="Admin créé"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Accès interdit"),
     *     @OA\Response(response=422, description="Validation échouée")
     * )
     */
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
    public function verifyEmail(string $id, string $hash, Request $request): JsonResponse|\Illuminate\Http\Response|\Illuminate\Contracts\View\View
    {
        Log::info('VerifyEmail called with ID: '.$id);

        // Validate the signed URL (checks signature + expiry)
        if (!$request->hasValidSignature()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Lien de vérification invalide ou expiré.'], 400);
            }

            return abort(400, 'Lien de vérification invalide ou expiré.');
        }

        if (!Str::isUuid($id)) {
            Log::warning('Invalid UUID provided: '.$id);
            if ($request->wantsJson()) {
                return response()->json(['message' => 'ID utilisateur invalide.'], 400);
            }

            return abort(400, 'ID utilisateur invalide.');
        }

        try {
            $user = User::findOrFail($id);

            // Defense-in-depth: verify hash matches user's email via HMAC-SHA256
            if (
                !hash_equals(
                    hash_hmac('sha256', (string) $user->getEmailForVerification(), (string) config('app.key')),
                    $hash
                )
            ) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'Lien de vérification invalide'], 400);
                }

                return abort(400, 'Lien de vérification invalide.');
            }

            if ($user->hasVerifiedEmail()) {
                return $request->wantsJson()
                    ? response()->json(['message' => 'Email déjà vérifié.', 'verified' => true])
                    : view('auth.verified', [
                        'loginUrl' => $user->role === \App\Enums\UserRole::ADMIN
                            ? config('app.url').'/admin'
                            : config('app.frontend_url').'/login',
                    ]);
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
                : view('auth.verified', [
                    'loginUrl' => $user->role === \App\Enums\UserRole::ADMIN
                        ? config('app.url').'/admin'
                        : config('app.frontend_url').'/login',
                ]);

        } catch (ModelNotFoundException) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Utilisateur non trouvé.'], 404);
            }

            return abort(404, 'Utilisateur non trouvé.');

        } catch (Throwable $e) {
            Log::error('Email verification failed', [
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
                'user_id' => $id,
            ]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Erreur lors de la vérification.'], 500);
            }

            return abort(500, 'Erreur lors de la vérification.');
        }
    }

    /**
     * Verify a 6-digit OTP code sent to the user's email after registration.
     *
     * On success the email is marked as verified, a Sanctum token is issued,
     * and the user is returned so the frontend can auto-login.
     *
     * @OA\Post(
     *     path="/api/v1/auth/verify-email-otp",
     *     tags={"🔐 Authentification"},
     *     summary="Vérifier le code OTP email",
     *     description="Vérifie le code OTP à 6 chiffres envoyé par email après l'inscription et connecte automatiquement l'utilisateur.",
     *     operationId="verifyEmailOtp",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email", "otp"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="jean.dupont@example.com"),
     *             @OA\Property(property="otp", type="string", example="123456", description="Code OTP à 6 chiffres")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email vérifié avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Email vérifié avec succès."),
     *             @OA\Property(property="verified", type="boolean", example=true),
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Code invalide ou expiré"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Trop de tentatives")
     * )
     */
    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $rateLimitKey = 'verify-email-otp:'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans '.$seconds.' secondes.',
            ], 429);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            RateLimiter::hit($rateLimitKey, 300);

            return response()->json([
                'message' => 'Code invalide ou expiré.',
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email déjà vérifié.',
                'verified' => true,
            ]);
        }

        $cachedOtp = Cache::get('email_otp_'.$user->id);

        if (!$cachedOtp || !hash_equals((string) $cachedOtp, $request->input('otp'))) {
            RateLimiter::hit($rateLimitKey, 300);

            return response()->json([
                'message' => 'Code invalide ou expiré.',
            ], 400);
        }

        Cache::forget('email_otp_'.$user->id);
        $user->markEmailAsVerified();
        event(new Verified($user));

        RateLimiter::clear($rateLimitKey);

        Log::info('Email verified via OTP', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        $user->tokens()->delete();
        $token = $user->createToken(
            'auth_token_'.now()->timestamp,
            ['*'],
            now()->addDays(7)
        );

        return response()->json([
            'message' => 'Email vérifié avec succès.',
            'verified' => true,
            'access_token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ]);
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

        // Rate limiting keyed on IP to protect against enumeration
        $key = 'resend-verification:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Trop de demandes. Réessayez dans '.$seconds.' secondes.',
            ], 429);
        }

        RateLimiter::hit($key, 300); // 5 minutes

        $user = User::where('email', $request->email)->first();

        // Always return 200 to prevent account enumeration
        if (!$user || $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Si cette adresse est enregistrée et non vérifiée, un email a été envoyé.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Si cette adresse est enregistrée et non vérifiée, un email a été envoyé.',
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
            if (!$user || !Hash::check($password, $user->password)) {
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
            if (isset($user->is_active) && !$user->is_active) {
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

            // SPA Authentication: Log in the user to the session if available
            if ($request->hasSession()) {
                $request->session()->regenerate();
                // Use the web guard to authenticate the session
                \Illuminate\Support\Facades\Auth::guard('web')->login($user);
            }

            // Créer le token avec expiration
            $tokenName = 'api_token_'.now()->timestamp;

            // Limit simultaneous tokens: revoke old api_token_ tokens
            $user->tokens()->where('name', 'like', 'api_token_%')->delete();

            $token = $user->createToken(
                $tokenName,
                ['*'], // abilities (permissions du token)
                now()->addDays(7) // expiration dans 7 jours
            );

            // ── Detect new geographic location (style Binance) ──────────────────────
            // Priority order for location data:
            //   1. Cloudflare headers (CF-IPCountry / CF-IPCity) – free & accurate
            //   2. Stevebauman/location or manual GeoIP – not installed, skip
            //   3. Fallback: raw IP comparison (legacy NewDeviceSignInMail)
            $currentIp = $request->ip();
            $cfCountry = $request->header('CF-IPCountry', '');
            $cfCity = $request->header('CF-IPCity', '');

            // Normalise location strings
            $currentCountry = strtoupper(trim($cfCountry));
            $currentCity = mb_convert_case(trim($cfCity), MB_CASE_TITLE);

            $knownCountry = $user->last_login_country ?? '';
            $knownCity = $user->last_login_city ?? '';
            $knownIp = $user->last_login_ip ?? '';

            $locationChanged = $knownCountry !== '' && (
                $currentCountry !== $knownCountry ||
                ($currentCity !== '' && $currentCity !== $knownCity)
            );

            $ua = UserAgentParser::parse($request->userAgent() ?? '');

            if ($locationChanged) {
                // New country or city → send the "new location" security email
                Mail::to($user->email, $user->firstname)->queue(new NewLocationSignInMail(
                    userName: $user->firstname ?? $user->email,
                    city: $currentCity ?: 'Inconnue',
                    country: $currentCountry ?: 'Inconnu',
                    ipAddress: $currentIp,
                    device: $ua['device_type'],
                    browser: $ua['browser_name'],
                    operatingSystem: $ua['operating_system'],
                    loginAt: now()->translatedFormat('d F Y \\à H:i'),
                    secureAccountUrl: config('app.frontend_url').'/security/sessions',
                    supportEmail: config('mail.from.address'),
                ));
            } elseif ($currentIp !== $knownIp && $knownIp !== '') {
                // Same country/city but different IP (e.g. new ISP) → existing device mail
                Mail::to($user->email, $user->firstname)->queue(new NewDeviceSignInMail(
                    deviceType: $ua['device_type'],
                    browserName: $ua['browser_name'],
                    operatingSystem: $ua['operating_system'],
                    location: ($currentCity ?: $currentIp).', '.$currentCountry,
                    ipAddress: $currentIp,
                    sessionCreatedAt: now()->translatedFormat('d F Y \\à H:i'),
                    signInMethod: 'Email / Mot de passe',
                    revokeSessionUrl: config('app.frontend_url').'/security/sessions',
                    supportEmail: config('mail.from.address'),
                ));
            }

            // Mettre à jour les informations de connexion
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $currentIp,
                'last_login_country' => $currentCountry ?: null,
                'last_login_city' => $currentCity ?: null,
            ])->save();

            // Log de connexion réussie
            Log::info('Successful login', [
                'user_id' => $user->id,
                'email' => $email,
                'is_spa' => $request->hasSession(), // Log context check
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
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
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
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // 1. Revoke Token (Mobile/API)
            // @phpstan-ignore-next-line
            if ($token = $user->currentAccessToken()) {
                // Log de déconnexion
                Log::info('User logout (Token)', [
                    'user_id' => $user->id,
                    'token_name' => $token->name,
                ]);

                // Supprimer le token actuel
                $token->delete();
            }

            // 2. Invalidate Session (SPA/Web)
            if ($request->hasSession()) {
                Log::info('User logout (Session)', [
                    'user_id' => $user->id,
                ]);

                \Illuminate\Support\Facades\Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'Déconnexion réussie.',
            ], 200);

        } catch (Throwable $e) {
            Log::error('Logout error', [
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
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
    public function me(Request $request): \App\Http\Resources\UserResource
    {
        return new UserResource($request->user()->load(['agency', 'city']));
    }

    /**
     * Mark the authenticated user's onboarding as completed.
     *
     * Called by the frontend after the WelcomeModal is dismissed.
     * Idempotent — subsequent calls are no-ops.
     */
    public function completeOnboarding(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->onboarding_completed_at) {
            $user->onboarding_completed_at = now();
            $user->save();
        }

        return response()->json(['message' => 'Onboarding complété.']);
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
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $request->user()->currentAccessToken();

            // Créer un nouveau token avec abilities normalisées
            $newToken = $user->createToken(
                'refreshed_token_'.now()->timestamp,
                ['*'],
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
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
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

        // Send reset link — always return 200 to prevent account enumeration
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'Si cette adresse est enregistrée, un email de réinitialisation a été envoyé.']);
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
            function ($user, $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Revoke all existing API tokens to invalidate compromised sessions
                $user->tokens()->delete();

                Mail::to($user->email, $user->firstname)
                    ->queue(new PasswordChangedMail($user->email, $user->firstname));

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

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Le mot de passe actuel est incorrect.'], 422);
        }

        $user->fill([
            'password' => Hash::make($request->new_password),
        ])->save();

        // Revoke all existing tokens except the current one
        $currentToken = $user->currentAccessToken();
        if ($currentToken instanceof \Laravel\Sanctum\PersonalAccessToken) { // @phpstan-ignore instanceof.alwaysTrue (TransientToken possible at runtime)
            $user->tokens()->where('id', '!=', $currentToken->getKey())->delete();
        } else {
            $user->tokens()->delete();
        }

        Mail::to($user->email, $user->firstname)
            ->queue(new PasswordChangedMail($user->email, $user->firstname));

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }

    /**
     * Exchange a Clerk JWT for a Sanctum token.
     * Creates a new user account or returns an existing one.
     *
     * @OA\Post(
     *     path="/api/v1/auth/clerk/exchange",
     *     summary="Échanger un token Clerk contre un token Sanctum",
     *     description="Valide le JWT Clerk et retourne un token Sanctum. Si l'utilisateur n'existe pas, déclenche un flux OTP pour vérifier l'email avant création.",
     *     tags={"🔐 Authentification"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Authentification réussie ou OTP requis",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string", nullable=true),
     *             @OA\Property(property="state", type="string", enum={"authenticated", "otp_required"}),
     *             @OA\Property(property="email_hint", type="string", nullable=true, example="j***@gmail.com")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Token Clerk invalide")
     * )
     */
    public function clerkExchange(ClerkExchangeRequest $request, ClerkJwtService $clerk): JsonResponse
    {
        /** @var string $bearerToken */
        $bearerToken = $request->bearerToken();

        $clerkUser = $clerk->verifyAndFetchUser($bearerToken);

        if ($clerkUser === null) {
            return response()->json(['message' => 'Token Clerk invalide ou expiré.'], 401);
        }

        $clerkId = (string) ($clerkUser['id'] ?? '');
        $firstName = (string) ($clerkUser['first_name'] ?? 'Utilisateur');
        $lastName = (string) ($clerkUser['last_name'] ?? '');
        $avatar = isset($clerkUser['image_url']) ? (string) $clerkUser['image_url'] : null;

        $emailAddresses = $clerkUser['email_addresses'] ?? [];
        $primaryEmailId = $clerkUser['primary_email_address_id'] ?? null;

        $email = null;
        foreach ($emailAddresses as $addr) {
            if ($primaryEmailId !== null && ($addr['id'] ?? null) === $primaryEmailId) {
                $email = $addr['email_address'];
                break;
            }
        }

        if ($email === null && count($emailAddresses) > 0) {
            $email = $emailAddresses[0]['email_address'] ?? null;
        }

        // Priority: match by clerk_id first; fallback to email for cross-provider linking
        $user = User::query()->where('clerk_id', $clerkId)->first()
            ?? ($email !== null ? User::query()->where('email', $email)->first() : null);

        // ── Existing user ─────────────────────────────────────────────────────
        if ($user !== null) {
            // Update clerk_id if missing or if user signed in via a different OAuth provider
            if ($user->clerk_id === null || $user->clerk_id !== $clerkId) {
                $user->update(['clerk_id' => $clerkId]);
            }

            $token = $user->createToken('clerk-exchange', ['*'], now()->addDays(7));

            auth()->setUser($user);

            return response()->json([
                'access_token' => $token->plainTextToken,
                'user' => new UserResource($user),
                'panel_sso_url' => $this->buildPanelSsoUrl($user),
            ]);
        }

        // ── New user — Send OTP to verify email before profile creation ─────────
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put('clerk_otp_'.$clerkId, $otp, now()->addMinutes(10));
        Cache::put('clerk_pending_'.$clerkId, [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => $email,
            'avatar' => $avatar,
        ], now()->addMinutes(15));

        if ($email !== null) {
            $requestedFrom = request()->ip() ?? 'inconnu';
            $requestedAt = now()->translatedFormat('d F Y à H:i');

            Mail::to($email, $firstName)
                ->queue(new VerificationCodeMail($otp, $requestedFrom, $requestedAt));
        }

        return response()->json([
            'state' => 'otp_required',
            'email_hint' => $email !== null ? $this->maskEmail($email) : null,
        ]);
    }

    /**
     * Verify the 6-digit OTP sent after a new OAuth sign-in.
     * Returns either an authenticated state (existing user) or profile_required (new user).
     *
     * @OA\Post(
     *     path="/api/v1/auth/clerk/verify-otp",
     *     summary="Vérifier le code OTP Clerk",
     *     description="Valide le code OTP reçu par email après un premier échange Clerk. Si l'utilisateur existe, retourne un token. Sinon, demande de compléter le profil.",
     *     tags={"🔐 Authentification"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"otp"},
     *
     *             @OA\Property(property="otp", type="string", example="123456", description="Code OTP à 6 chiffres")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="OTP validé",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="state", type="string", enum={"authenticated", "profile_required"}),
     *             @OA\Property(property="access_token", type="string", nullable=true),
     *             @OA\Property(property="prefill", type="object", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Token Clerk invalide"),
     *     @OA\Response(response=422, description="Code OTP invalide ou expiré")
     * )
     */
    public function verifyClerkOtp(Request $request, ClerkJwtService $clerk): JsonResponse
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            return response()->json(['message' => 'Token non fourni.'], 401);
        }

        $clerkUser = $clerk->verifyAndFetchUser($bearerToken);

        if ($clerkUser === null) {
            return response()->json(['message' => 'Token Clerk invalide ou expiré.'], 401);
        }

        $clerkId = (string) ($clerkUser['id'] ?? '');
        $otp = (string) ($request->input('otp', ''));
        $cachedOtp = Cache::get('clerk_otp_'.$clerkId);

        if ($cachedOtp === null || !hash_equals($cachedOtp, $otp)) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        Cache::forget('clerk_otp_'.$clerkId);
        Cache::put('clerk_verified_'.$clerkId, true, now()->addMinutes(15));

        // Resolve email from Clerk payload
        $emailAddresses = $clerkUser['email_addresses'] ?? [];
        $primaryEmailId = $clerkUser['primary_email_address_id'] ?? null;
        $email = null;

        foreach ($emailAddresses as $addr) {
            if ($primaryEmailId !== null && ($addr['id'] ?? null) === $primaryEmailId) {
                $email = $addr['email_address'];
                break;
            }
        }

        if ($email === null && count($emailAddresses) > 0) {
            $email = $emailAddresses[0]['email_address'] ?? null;
        }

        // Priority: match by clerk_id first; fallback to email only for accounts not yet linked
        $user = User::query()->where('clerk_id', $clerkId)->first()
            ?? ($email !== null ? User::query()->whereNull('clerk_id')->where('email', $email)->first() : null);

        if ($user !== null) {
            if ($user->clerk_id === null) {
                $user->update(['clerk_id' => $clerkId]);
            }

            Cache::forget('clerk_verified_'.$clerkId);
            Cache::forget('clerk_pending_'.$clerkId);

            $token = $user->createToken('clerk-exchange', ['*'], now()->addDays(7));
            auth()->setUser($user);

            return response()->json([
                'state' => 'authenticated',
                'access_token' => $token->plainTextToken,
                'user' => new UserResource($user),
                'panel_sso_url' => $this->buildPanelSsoUrl($user),
            ]);
        }

        /** @var array{firstname: string, lastname: string, email: string|null, avatar: string|null} $pending */
        $pending = Cache::get('clerk_pending_'.$clerkId, []);

        return response()->json([
            'state' => 'profile_required',
            'prefill' => $pending,
        ]);
    }

    /**
     * Complete profile creation for a new OAuth user after OTP verification.
     * Creates the Laravel account, sends a welcome email, and returns a Sanctum token.
     *
     * @OA\Post(
     *     path="/api/v1/auth/clerk/complete-profile",
     *     summary="Compléter le profil après vérification OTP",
     *     description="Crée le compte utilisateur Laravel après que l'email ait été vérifié par OTP. Retourne un token Sanctum et les infos utilisateur.",
     *     tags={"🔐 Authentification"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"phone_number"},
     *
     *             @OA\Property(property="phone_number", type="string", example="+229 97 00 00 00"),
     *             @OA\Property(property="city_id", type="string", format="uuid", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Compte créé avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="user", ref="#/components/schemas/UserResource")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Token Clerk invalide"),
     *     @OA\Response(response=403, description="Vérification email requise"),
     *     @OA\Response(response=422, description="Numéro de téléphone manquant")
     * )
     */
    public function completeClerkProfile(ClerkExchangeRequest $request, ClerkJwtService $clerk): JsonResponse
    {
        $bearerToken = $request->bearerToken();

        $clerkUser = $clerk->verifyAndFetchUser($bearerToken);

        if ($clerkUser === null) {
            return response()->json(['message' => 'Token Clerk invalide ou expiré.'], 401);
        }

        $clerkId = (string) ($clerkUser['id'] ?? '');

        if (!Cache::get('clerk_verified_'.$clerkId)) {
            return response()->json(['message' => 'Vérification email requise.'], 403);
        }

        if (!$request->filled('phone_number')) {
            return response()->json(['message' => 'Le numéro de téléphone est obligatoire.'], 422);
        }

        /** @var array{firstname?: string, lastname?: string, email?: string|null, avatar?: string|null} $pending */
        $pending = Cache::get('clerk_pending_'.$clerkId, []);
        $firstName = (string) ($pending['firstname'] ?? $clerkUser['first_name'] ?? 'Utilisateur');
        $lastName = (string) ($pending['lastname'] ?? $clerkUser['last_name'] ?? '');
        $avatar = $pending['avatar'] ?? (isset($clerkUser['image_url']) ? (string) $clerkUser['image_url'] : null);
        $email = $pending['email'] ?? null;

        if ($email === null) {
            $emailAddresses = $clerkUser['email_addresses'] ?? [];
            $primaryEmailId = $clerkUser['primary_email_address_id'] ?? null;

            foreach ($emailAddresses as $addr) {
                if ($primaryEmailId !== null && ($addr['id'] ?? null) === $primaryEmailId) {
                    $email = $addr['email_address'];
                    break;
                }
            }

            if ($email === null && count($emailAddresses) > 0) {
                $email = $emailAddresses[0]['email_address'] ?? null;
            }
        }

        // Guard against race-condition double-creation
        // Priority: match by clerk_id first; fallback to email only for accounts not yet linked
        $user = User::query()->where('clerk_id', $clerkId)->first()
            ?? ($email !== null ? User::query()->whereNull('clerk_id')->where('email', $email)->first() : null);

        $isNew = false;

        if ($user === null) {
            $user = new User;
            $user->fill([
                'clerk_id' => $clerkId,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $email ?? $clerkId.'@clerk.local',
                'phone_number' => $request->input('phone_number'),
                'city_id' => $request->input('city_id'),
                'avatar' => $avatar,
            ]);
            $user->forceFill([
                'role' => UserRole::CUSTOMER,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->save();

            $isNew = true;

            if ($email !== null && !str_ends_with((string) $email, '@clerk.local')) {
                Mail::to($email, $firstName)->queue(new WelcomeEmail($user));
            }
        } else {
            if ($user->clerk_id === null) {
                $user->update(['clerk_id' => $clerkId]);
            }
        }

        Cache::forget('clerk_verified_'.$clerkId);
        Cache::forget('clerk_pending_'.$clerkId);

        $token = $user->createToken('clerk-exchange', ['*'], now()->addDays(7));
        auth()->setUser($user);

        return response()->json([
            'access_token' => $token->plainTextToken,
            'user' => new UserResource($user),
            'panel_sso_url' => $this->buildPanelSsoUrl($user),
        ], $isNew ? 201 : 200);
    }

    /**
     * Mask an email address for display: j***@gmail.com
     */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $masked = mb_substr($local, 0, 1).str_repeat('*', max(3, mb_strlen($local) - 1));

        return $masked.'@'.$domain;
    }

    /**
     * Generate a short-lived signed URL for panel auto-login.
     * Returns null for customer accounts (they stay on the frontend).
     */
    private function buildPanelSsoUrl(User $user): ?string
    {
        if ($user->role === UserRole::CUSTOMER) {
            return null;
        }

        return URL::temporarySignedRoute(
            'panel.sso',
            now()->addSeconds(60),
            ['user_id' => $user->id]
        );
    }
}
