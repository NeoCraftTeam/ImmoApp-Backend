<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ApiMfaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClerkAuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use App\Http\Controllers\Api\V1\UserPreferenceController;
use App\Http\Controllers\EmailPreferenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    // Registration
    Route::post('registerCustomer', [RegistrationController::class, 'registerCustomer'])
        ->middleware('throttle:auth.register');
    Route::post('registerAgent', [RegistrationController::class, 'registerAgent'])
        ->middleware('throttle:auth.register');
    // Admin registration — auth:sanctum MUST come before can:admin-access so the
    // gate receives an authenticated user; unauthenticated requests get 401, not 403.
    Route::post('registerAdmin', [RegistrationController::class, 'registerAdmin'])
        ->middleware(['auth:sanctum', 'can:admin-access', 'throttle:auth.register']);
    // Lowered to 5/min — email enumeration mitigation (W15)
    Route::post('check-email', [RegistrationController::class, 'checkEmail'])
        ->middleware('throttle:5,1');

    // Login
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:auth.login');

    // Email verification
    Route::post('resend-verification', [EmailVerificationController::class, 'resendVerificationEmail'])
        ->middleware('throttle:auth.resend-verify');
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verifyEmail'])
        ->middleware('throttle:auth.verify-email')
        ->name('api.verification.verify');
    Route::post('verify-email-otp', [EmailVerificationController::class, 'verifyEmailOtp'])
        ->middleware('throttle:auth.verify-otp');

    // Password Reset
    Route::post('forgot-password', [PasswordController::class, 'forgotPassword'])->middleware('throttle:auth.password-reset');
    Route::post('reset-password', [PasswordController::class, 'resetPassword'])->middleware('throttle:auth.password-reset');

    // Clerk JWT → Sanctum token exchange
    Route::post('clerk/exchange', [ClerkAuthController::class, 'clerkExchange'])->middleware('throttle:auth.clerk');
    Route::post('clerk/verify-otp', [ClerkAuthController::class, 'verifyClerkOtp'])->middleware('throttle:auth.clerk-otp');
    Route::post('clerk/complete-profile', [ClerkAuthController::class, 'completeClerkProfile'])->middleware('throttle:auth.clerk-otp');

    // OAuth Social Authentication
    Route::prefix('oauth')->controller(SocialAuthController::class)->group(function (): void {
        Route::post('{provider}', 'authenticate')
            ->middleware('throttle:auth.social')
            ->where('provider', 'google|facebook|apple');

        Route::get('{provider}/redirect', 'redirect')
            ->middleware('throttle:auth.social')
            ->where('provider', 'google|facebook|apple');

        Route::get('{provider}/callback', 'callback')
            ->middleware('throttle:auth.social')
            ->where('provider', 'google|facebook|apple');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('{provider}/link', 'link')
                ->where('provider', 'google|facebook|apple');

            Route::delete('{provider}/unlink', 'unlink')
                ->where('provider', 'google|facebook|apple');
        });

        Route::post('confirm-link', 'confirmOAuthLink')
            ->middleware('throttle:auth.update-password');

        // Redeem a short-lived exchange code (from OAuth callback) for a Sanctum token.
        // Exchange codes are stored in cache for 2 minutes after OAuth callback.
        Route::get('exchange-token', 'exchangeToken')
            ->middleware('throttle:20,1');
    });

    // MFA for admin API access
    Route::middleware('auth:sanctum')->prefix('mfa')->controller(ApiMfaController::class)->group(function (): void {
        Route::get('/status', 'status')->middleware('throttle:60,1');
        Route::post('/verify', 'verify')->middleware('throttle:10,1');
    });

    // Authenticated auth routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('refresh', [AuthController::class, 'refresh'])
            ->middleware('throttle:auth.general');
        Route::get('me', [AuthController::class, 'me']);
        Route::post('email/resend', [EmailVerificationController::class, 'resendVerificationEmail'])
            ->middleware('throttle:auth.resend-verify');
        Route::post('update-password', [PasswordController::class, 'updatePassword'])->middleware('throttle:auth.update-password');
        Route::post('onboarding-complete', [UserPreferenceController::class, 'completeOnboarding'])
            ->middleware('throttle:auth.general');
        Route::post('track-home-visit', [UserPreferenceController::class, 'trackHomeVisit'])
            ->middleware('throttle:auth.general');
        Route::patch('preferences', [UserPreferenceController::class, 'updatePreferences'])
            ->middleware('throttle:auth.general');
        Route::patch('locale', [UserPreferenceController::class, 'updateLocale'])
            ->middleware('throttle:auth.general');
        Route::get('email-preferences', [EmailPreferenceController::class, 'show'])
            ->middleware('throttle:auth.general');
        Route::patch('email-preferences', [EmailPreferenceController::class, 'apiUpdate'])
            ->middleware('throttle:auth.general');
    });
});
