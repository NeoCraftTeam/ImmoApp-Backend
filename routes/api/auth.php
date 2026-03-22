<?php

declare(strict_types=1);

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
        ->middleware('throttle:5,1');
    Route::post('registerAgent', [RegistrationController::class, 'registerAgent'])
        ->middleware('throttle:5,1');

    // Login
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // Email verification
    Route::post('resend-verification', [EmailVerificationController::class, 'resendVerificationEmail'])
        ->middleware('throttle:2,5');
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verifyEmail'])
        ->middleware('throttle:5,10')
        ->name('api.verification.verify');
    Route::post('verify-email-otp', [EmailVerificationController::class, 'verifyEmailOtp'])
        ->middleware('throttle:5,1');

    // Password Reset
    Route::post('forgot-password', [PasswordController::class, 'forgotPassword'])->middleware('throttle:3,10');
    Route::post('reset-password', [PasswordController::class, 'resetPassword'])->middleware('throttle:3,10');

    // Clerk JWT → Sanctum token exchange
    Route::post('clerk/exchange', [ClerkAuthController::class, 'clerkExchange'])->middleware('throttle:10,1');
    Route::post('clerk/verify-otp', [ClerkAuthController::class, 'verifyClerkOtp'])->middleware('throttle:5,1');
    Route::post('clerk/complete-profile', [ClerkAuthController::class, 'completeClerkProfile'])->middleware('throttle:5,1');

    // OAuth Social Authentication
    Route::prefix('oauth')->controller(SocialAuthController::class)->group(function (): void {
        Route::post('{provider}', 'authenticate')
            ->middleware('throttle:10,1')
            ->where('provider', 'google|facebook|apple');

        Route::get('{provider}/redirect', 'redirect')
            ->middleware('throttle:10,1')
            ->where('provider', 'google|facebook|apple');

        Route::get('{provider}/callback', 'callback')
            ->middleware('throttle:10,1')
            ->where('provider', 'google|facebook|apple');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('{provider}/link', 'link')
                ->where('provider', 'google|facebook|apple');

            Route::delete('{provider}/unlink', 'unlink')
                ->where('provider', 'google|facebook|apple');
        });

        Route::post('confirm-link', 'confirmOAuthLink')
            ->middleware('throttle:5,10');
    });

    // Authenticated auth routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('registerAdmin', [RegistrationController::class, 'registerAdmin'])
            ->middleware('can:admin-access');
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('email/resend', [EmailVerificationController::class, 'resendVerificationEmail']);
        Route::post('update-password', [PasswordController::class, 'updatePassword'])->middleware('throttle:5,10');
        Route::post('onboarding-complete', [UserPreferenceController::class, 'completeOnboarding']);
        Route::post('track-home-visit', [UserPreferenceController::class, 'trackHomeVisit']);
        Route::patch('preferences', [UserPreferenceController::class, 'updatePreferences']);
        Route::patch('locale', [UserPreferenceController::class, 'updateLocale']);
        Route::get('email-preferences', [EmailPreferenceController::class, 'show']);
        Route::patch('email-preferences', [EmailPreferenceController::class, 'apiUpdate']);
    });
});
