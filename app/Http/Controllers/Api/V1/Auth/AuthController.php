<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\SuccessCode;
use App\Exceptions\AccountInactiveException;
use App\Exceptions\EmailNotVerifiedException;
use App\Exceptions\RoleContextMismatchException;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\LoginService;
use App\Services\Auth\TokenService;
use App\Support\ApiResponse;
use App\Support\AuthError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
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
final readonly class AuthController
{
    public function __construct(
        private TokenService $tokenService,
        private LoginService $loginService,
    ) {}

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
            $result = $this->loginService->authenticate($request);

            if ($request->hasSession()) {
                $request->session()->regenerate();
                Auth::guard('web')->login($result->user);
            }

            return response()->json([
                'message' => 'Connexion réussie.',
                'access_token' => $result->token->plainTextToken,
                'expires_at' => $result->token->accessToken->expires_at,
                'role' => $result->user->role->value,
                'type' => $result->user->type?->value,
            ]);

        } catch (AuthenticationException) {
            return AuthError::loginFailure();

        } catch (EmailNotVerifiedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'email_verification_required' => true,
                'email' => $e->email,
                'role' => $e->role,
            ], 403);

        } catch (AccountInactiveException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);

        } catch (RoleContextMismatchException $e) {
            return AuthError::loginPanelMismatch(code: $e->authCode);

        } catch (Throwable $e) {
            Log::error('Login error', [
                'exception' => $e->getMessage(),
                'request_data' => $request->except(['password']),
            ]);
            throw $e;
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

            $token = $user->currentAccessToken();

            // TransientToken (Clerk JWT exchange) has no delete() — only revoke DB-backed tokens.
            if ($token instanceof PersonalAccessToken) {
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

            return ApiResponse::successCode(SuccessCode::Logout);

        } catch (Throwable $e) {
            Log::error('Logout error', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            throw $e;
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
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            throw $e;
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
    public function me(Request $request): UserResource|JsonResponse
    {
        $user = $request->user();

        // Reject users with unverified email to prevent AuthProvider from
        // treating them as authenticated before OTP/email verification.
        // Le payload porte l'email et l'id du compte (déjà identifié par
        // son bearer token) pour que le client puisse router vers l'écran
        // OTP et proposer la correction d'une adresse mal saisie.
        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Email non vérifié.',
                'email_verification_required' => true,
                'email' => $user->email,
                'user_id' => $user->id,
            ], 403);
        }

        return new UserResource($user->load(['agency', 'city']))
            ->additional([
                'role' => $user->role->value,
                'type' => $user->type?->value,
            ]);
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

            // Preserve the login-context prefix so a client-context refresh
            // does not accidentally produce an owner-prefixed token.
            // TransientToken (Clerk JWT exchange) has no $name property — default to role-based prefix.
            $isDbToken = $currentToken instanceof PersonalAccessToken;
            $prefix = ($isDbToken && str_starts_with((string) $currentToken->name, 'owner_'))
                ? 'owner'
                : 'client';

            // AUTH-5: Use rotateForUser() for DB-backed tokens so the family_id
            // propagates and the old token is soft-revoked (not hard-deleted).
            // This enables compromise detection in TokenService.
            // TransientToken (Clerk JWT) has no DB row — create a fresh token.
            //
            // The suffix stays `token` on purpose: compromise detection keys off
            // "no active token matches the revoke pattern, yet a revoked ancestor
            // does". Naming the rotated token `{prefix}_refreshed_…` put it
            // outside `{prefix}_token_%`, so the *next* rotation of the very same
            // session read as a stolen-token replay and revoked every session the
            // user owned — logging the user out of the web app, the mobile app and
            // the other panel at once. A rotation must leave behind an active token
            // that its own pattern still matches.
            $newToken = $isDbToken
                ? $this->tokenService->rotateForUser($user, 'token', "{$prefix}_token_%", $prefix)
                : $this->tokenService->createForUser($user, 'refreshed', $prefix);

            return response()->json([
                'access_token' => $newToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $newToken->accessToken->expires_at,
            ]);

        } catch (Throwable $e) {
            Log::error('Token refresh error', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            throw $e;
        }
    }
}
