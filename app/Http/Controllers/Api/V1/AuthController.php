<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Throwable;

class AuthController
{

    /**
     * Inscription utilisateur
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     * @throws FileIsTooBig
     * @throws FileDoesNotExist
     */
    private function registerUser(array $data, RegisterRequest $request)
    {
        try {
            // Vérifier le rate limiting personnalisé
            $key = 'register-attempts:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $seconds = RateLimiter::availableIn($key);

                Log::warning('Too many registration attempts', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                return response()->json([
                    'message' => 'Trop de tentatives d\'inscription. Réessayez dans ' . $seconds . ' secondes.',
                    'retry_after' => $seconds
                ], 429);
            }

            $data = $request->validated();

            // Vérifier si l'utilisateur existe déjà (double sécurité)
            if (User::where('email', $data['email'])->exists()) {
                RateLimiter::hit($key, 600); // 10 minutes de blocage

                Log::warning('Registration attempt with existing email', [
                    'email' => $data['email'],
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'message' => 'Cette adresse email est déjà utilisée.'
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
                    'role' => $data['role'] ?? 'user', // Valeur par défaut
                    'type' => $data['type'] ?? null,
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
                        ->usingName($user->firstname . '_' . $user->lastname . '_avatar')
                        ->toMediaCollection('avatars');
                }

                return $user;

            });

            // Réinitialiser les tentatives d'inscription échouées
            RateLimiter::clear($key);

            // Créer le token d'accès
            $token = $result->createToken(
                'registration_token_' . now()->timestamp,
            );

            // Log de succès
            Log::info('User registered successfully', [
                'user_id' => $result->id,
                'email' => $result->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'message' => 'Inscription réussie.',
                'user' => new UserResource($result),
                'access_token' => $token->plainTextToken,
                'email_verification_required' => $result->email_verified_at === null,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation.',
                'errors' => $e->errors()
            ], 422);

        } catch (FileIsTooBig $e) {
            Log::warning('File too big during registration', [
                'error' => $e->getMessage(),
                'request_data' => $request->except(['password', 'avatar'])
            ]);

            return response()->json([
                'message' => 'Le fichier avatar est trop volumineux.',
                'max_size' => '2MB'
            ], 413);

        } catch (FileDoesNotExist $e) {
            Log::warning('File does not exist during registration', [
                'error' => $e->getMessage(),
                'request_data' => $request->except(['password', 'avatar'])
            ]);

            return response()->json([
                'message' => 'Le fichier avatar est introuvable.'
            ], 400);

        } catch (Throwable $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password', 'avatar'])
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function registerCustomer(RegisterRequest $request)
    {
        $data = $request->validated();
        $data['role'] = 'customer';

        // Appel de la méthode privée
        return $this->registerUser($data, $request);
    }


    public function registerAgent(RegisterRequest $request)
    {
        $data = $request->validated();
        $data['role'] = 'agent';

        // Appel de la méthode privée
        return $this->registerUser($data, $request);
    }


    /**
     * Vérifier l'adresse email
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'hash' => 'required|string',
        ]);

        $user = User::findOrFail($request->id);

        if (!hash_equals(sha1($user->email), $request->hash)) {
            return response()->json([
                'message' => 'Lien de vérification invalide.'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email déjà vérifié.'
            ], 200);
        }

        $user->markEmailAsVerified();

        Log::info('Email verified', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json([
            'message' => 'Email vérifié avec succès.'
        ], 200);
    }

    /**
     * Renvoyer l'email de vérification
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resendVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non trouvé.'
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email déjà vérifié.'
            ], 200);
        }

        // Rate limiting pour éviter le spam
        $key = 'resend-verification:' . $request->ip() . ':' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 2)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Trop de demandes. Réessayez dans ' . $seconds . ' secondes.'
            ], 429);
        }

        $user->sendEmailVerificationNotification();
        RateLimiter::hit($key, 300); // 5 minutes

        return response()->json([
            'message' => 'Email de vérification renvoyé.'
        ], 200);
    }

    /**
     * Connexion utilisateur
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->validated();
            $email = $credentials['email'];
            $password = $credentials['password'];

            // Vérifier le rate limiting personnalisé
            $key = 'login-attempts:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);

                Log::warning('Too many login attempts', [
                    'ip' => $request->ip(),
                    'email' => $email,
                    'user_agent' => $request->userAgent()
                ]);

                return response()->json([
                    'message' => 'Trop de tentatives de connexion. Réessayez dans ' . $seconds . ' secondes.',
                    'retry_after' => $seconds
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
                    'timestamp' => now()
                ]);

                return response()->json([
                    'message' => 'Identifiants invalides.'
                ], 401);
            }

            // Vérifier si le compte est actif
            if (isset($user->is_active) && !$user->is_active) {
                Log::info('Login attempt on inactive account', [
                    'user_id' => $user->id,
                    'email' => $email
                ]);

                return response()->json([
                    'message' => 'Compte désactivé. Contactez l\'administrateur.'
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
            $tokenName = 'api_token_' . now()->timestamp;

            $token = $user->createToken(
                $tokenName,
            );

            // Mettre à jour les informations de connexion
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip()
            ]);

            // Log de connexion réussie
            Log::info('Successful login', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'message' => 'Connexion réussie.',
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Données de validation invalides.',
                'errors' => $e->errors()
            ], 422);

        } catch (Throwable $e) {
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password'])
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la connexion.'
            ], 500);
        }
    }

    /**
     * Déconnexion
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $token = $request->user()->currentAccessToken();

            // Log de déconnexion
            Log::info('User logout', [
                'user_id' => $user->id,
                'token_name' => $token->name
            ]);

            // Supprimer le token actuel
            $token->delete();

            return response()->json([
                'message' => 'Déconnexion réussie.'
            ], 200);

        } catch (Throwable $e) {
            Log::error('Logout error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'message' => 'Erreur lors de la déconnexion.'
            ], 500);
        }
    }

    /**
     * Informations utilisateur connecté
     *
     * @param Request $request
     * @return UserResource
     */
    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    /**
     * Rafraîchir le token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request)
    {
        try {
            $user = $request->user();
            $currentToken = $request->user()->currentAccessToken();

            // Créer un nouveau token
            $newToken = $user->createToken(
                'refreshed_token_' . now()->timestamp,
                $currentToken->abilities,
                now()->addDay()
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
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'message' => 'Erreur lors du rafraîchissement du token.'
            ], 500);
        }
    }

}
