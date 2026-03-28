<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Mail\NewDeviceSignInMail;
use App\Mail\NewLocationSignInMail;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\UserAgentParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Handles session/token lifecycle: login, logout, me, refresh.
 *
 * Registration → RegistrationController
 * Email verification → EmailVerificationController
 * Password reset → PasswordController
 * Clerk OAuth → ClerkAuthController
 * User preferences → UserPreferenceController
 */
final class AuthController
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"🔐 Authentification"},
     *     summary="Connexion utilisateur",
     *     operationId="login",
     *
     *     @OA\Response(response=200, description="Connexion réussie"),
     *     @OA\Response(response=401, description="Identifiants invalides"),
     *     @OA\Response(response=403, description="Compte désactivé"),
     *     @OA\Response(response=429, description="Trop de tentatives")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();
            $email = $credentials['email'];
            $password = $credentials['password'];

            $key = 'login-attempts:'.$request->ip().'|'.mb_strtolower((string) $email);
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

            $user = User::where('email', $email)->first();

            if (!$user || !Hash::check($password, $user->password)) {
                RateLimiter::hit($key, 300);

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

            if (isset($user->is_active) && !$user->is_active) {
                Log::info('Login attempt on inactive account', [
                    'user_id' => $user->id,
                    'email' => $email,
                ]);

                return response()->json([
                    'message' => 'Compte désactivé. Contactez l\'administrateur.',
                ], 403);
            }

            if ($user->email_verified_at === null) {
                return response()->json([
                    'message' => 'Veuillez vérifier votre adresse email avant de vous connecter.',
                ], 403);
            }

            RateLimiter::clear($key);

            // Create session when available (SPA cookie auth); pure API clients skip this
            if ($request->hasSession()) {
                $request->session()->regenerate();
                Auth::guard('web')->login($user);
            }

            $tokenName = 'api_token_'.now()->timestamp;
            $user->tokens()->where('name', 'like', 'api_token_%')->delete();

            $token = $user->createToken(
                $tokenName,
                ['*'],
                now()->addDay()
            );

            $this->detectNewLocation($user, $request);

            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
                'last_login_country' => strtoupper(trim($request->header('CF-IPCountry', ''))) ?: null,
                'last_login_city' => mb_convert_case(trim($request->header('CF-IPCity', '')), MB_CASE_TITLE) ?: null,
            ])->save();

            $parsed = UserAgentParser::parse($request->userAgent() ?? '');

            LoginHistory::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $parsed['device_type'],
                'browser' => $parsed['browser_name'],
                'platform' => $parsed['operating_system'],
                'country' => strtoupper(trim($request->header('CF-IPCountry', ''))) ?: null,
                'city' => mb_convert_case(trim($request->header('CF-IPCity', '')), MB_CASE_TITLE) ?: null,
                'guard' => 'sanctum',
                'successful' => true,
            ]);

            Log::info('Successful login', [
                'user_id' => $user->id,
                'email' => $email,
                'is_spa' => $request->hasSession(),
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
     *     operationId="logout",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="Déconnexion réussie")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            /** @phpstan-ignore-next-line */
            if ($token = $user->currentAccessToken()) {
                Log::info('User logout (Token)', [
                    'user_id' => $user->id,
                    'token_name' => $token->name,
                ]);

                $token->delete();
            }

            if ($request->hasSession()) {
                Log::info('User logout (Session)', [
                    'user_id' => $user->id,
                ]);

                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'Déconnexion réussie.',
            ]);

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
     * @OA\Post(
     *     path="/api/v1/auth/logout-all",
     *     tags={"🔐 Authentification"},
     *     summary="Sign out from all devices",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="All sessions revoked")
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $count = $user->tokens()->count();

            $user->tokens()->delete();

            Log::info('User logout from all devices', [
                'user_id' => $user->id,
                'tokens_revoked' => $count,
            ]);

            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'Toutes les sessions ont été révoquées.',
                'tokens_revoked' => $count,
            ]);
        } catch (Throwable $e) {
            Log::error('Logout all error', [
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
     *     operationId="me",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="Informations utilisateur")
     * )
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['agency', 'city']));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/refresh",
     *     tags={"🔐 Authentification"},
     *     summary="Rafraîchir le token d'accès",
     *     operationId="refresh",
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="Token rafraîchi avec succès")
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $request->user()->currentAccessToken();

            $newToken = $user->createToken(
                'refreshed_token_'.now()->timestamp,
                ['*'],
                now()->addDay()
            );

            $currentToken->delete();

            return response()->json([
                'access_token' => $newToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $newToken->accessToken->expires_at,
            ]);

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
     * Detect new geographic location or IP and send security alert emails.
     */
    private function detectNewLocation(User $user, Request $request): void
    {
        $currentIp = $request->ip();
        $cfCountry = $request->header('CF-IPCountry', '');
        $cfCity = $request->header('CF-IPCity', '');

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
    }
}
