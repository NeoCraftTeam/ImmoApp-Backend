<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final readonly class EmailVerificationController
{
    public function __construct(private TokenService $tokenService) {}

    /**
     * @OA\Post(
     *     path="/api/v1/auth/verifyEmail",
     *     tags={"🔐 Authentification"},
     *     summary="Vérification de l'adresse email par lien signé",
     *     operationId="verifyEmail",
     *
     *     @OA\Response(response=200, description="Email vérifié avec succès"),
     *     @OA\Response(response=400, description="Lien invalide"),
     *     @OA\Response(response=404, description="Utilisateur non trouvé")
     * )
     */
    public function verifyEmail(string $id, string $hash, Request $request): JsonResponse|Response|View
    {
        Log::info('VerifyEmail called with ID: '.$id);

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

            $loginUrl = $user->role === UserRole::ADMIN
                ? config('app.url').'/admin'
                : config('app.frontend_url').'/login';

            if ($user->hasVerifiedEmail()) {
                return $request->wantsJson()
                    ? response()->json(['message' => 'Email déjà vérifié.', 'verified' => true])
                    : view('auth.verified', ['loginUrl' => $loginUrl]);
            }

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));

                Log::info('Email verified successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Email vérifié avec succès.', 'verified' => true])
                : view('auth.verified', ['loginUrl' => $loginUrl]);

        } catch (ModelNotFoundException) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Utilisateur non trouvé.'], 404);
            }

            return abort(404, 'Utilisateur non trouvé.');

        } catch (\Throwable $e) {
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
     * @OA\Post(
     *     path="/api/v1/auth/verify-email-otp",
     *     tags={"🔐 Authentification"},
     *     summary="Vérifier le code OTP email",
     *     operationId="verifyEmailOtp",
     *
     *     @OA\Response(response=200, description="Email vérifié avec succès"),
     *     @OA\Response(response=400, description="Code invalide ou expiré"),
     *     @OA\Response(response=429, description="Trop de tentatives")
     * )
     */
    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        // SEC: IP-level gate before any DB read — prevents unlimited lookups for
        // non-existent emails where the per-user limiter never fires.
        $ipKey = 'verify-email-otp:ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            $seconds = RateLimiter::availableIn($ipKey);

            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans '.$seconds.' secondes.',
                'retry_after' => $seconds,
            ], 429);
        }
        RateLimiter::hit($ipKey, 300);

        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return response()->json([
                'message' => 'Code invalide ou expiré.',
            ], 400);
        }

        $rateLimitKey = 'verify-email-otp:'.$user->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => 'Trop de tentatives. Réessayez dans '.$seconds.' secondes.',
            ], 429);
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

        $user->tokens()->where('name', 'like', '%_registration_%')->delete();

        $token = $this->tokenService->createForUser($user, 'auth');

        auth()->setUser($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
            Auth::guard('web')->login($user);
        }

        return response()->json([
            'message' => 'Email vérifié avec succès.',
            'verified' => true,
            'access_token' => $token->plainTextToken,
            'user' => new UserResource($user),
            // SEC: Always include role/type at top level so the frontend can
            // route correctly even if UserResource omits them (unauthenticated request).
            'role' => $user->role->value,
            'type' => $user->type?->value,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/resendVerificationEmail",
     *     tags={"🔐 Authentification"},
     *     summary="Renvoyer l'email de vérification",
     *     operationId="resendVerificationEmail",
     *
     *     @OA\Response(response=200, description="Email de vérification renvoyé"),
     *     @OA\Response(response=429, description="Trop de demandes")
     * )
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Si cette adresse est enregistrée et non vérifiée, un email a été envoyé.',
            ]);
        }

        $key = 'resend-verification:'.$user->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Trop de demandes. Réessayez dans '.$seconds.' secondes.',
            ], 429);
        }

        RateLimiter::hit($key, 300);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Si cette adresse est enregistrée et non vérifiée, un email a été envoyé.',
        ]);
    }
}
