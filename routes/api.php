<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdAnalyticsController;
use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\AdInteractionController;
use App\Http\Controllers\Api\V1\AdTypeController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\QuarterController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Prefix routes
Route::prefix('v1')->group(function (): void {

    //  Auth
    Route::prefix('auth')->controller(AuthController::class)->group(function (): void {
        // Public routes with throttling(rate limiting)
        Route::post('registerCustomer', [AuthController::class, 'registerCustomer'])
            ->middleware('throttle:5,1'); // 5 attempts per minute

        Route::post('registerAgent', [AuthController::class, 'registerAgent'])
            ->middleware('throttle:5,1'); //  5 attempts per minute

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1'); //  5 attempts per minute

        Route::post('resend-verification', [AuthController::class, 'resendVerificationEmail'])
            ->middleware('throttle:2,5'); // 2 attempts every 5 minutes

        Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('api.verification.verify');

        // Password Reset
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,10');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,10');

        // OAuth Social Authentication
        Route::prefix('oauth')->controller(SocialAuthController::class)->group(function (): void {
            // Public OAuth endpoints
            Route::post('{provider}', 'authenticate')
                ->middleware('throttle:10,1')
                ->where('provider', 'google|facebook|apple');

            Route::get('{provider}/redirect', 'redirect')
                ->where('provider', 'google|facebook|apple');

            Route::get('{provider}/callback', 'callback')
                ->where('provider', 'google|facebook|apple');

            // Authenticated OAuth endpoints (link/unlink)
            Route::middleware('auth:sanctum')->group(function (): void {
                Route::post('{provider}/link', 'link')
                    ->where('provider', 'google|facebook|apple');

                Route::delete('{provider}/unlink', 'unlink')
                    ->where('provider', 'google|facebook|apple');
            });
        });

        // Routes protégées
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('registerAdmin', [AuthController::class, 'registerAdmin'])
                ->middleware('can:admin-access');
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('email/resend', [AuthController::class, 'resendVerificationEmail'])->middleware('auth:sanctum');
            Route::post('update-password', [AuthController::class, 'updatePassword']);
        });
    });

    // adType
    Route::middleware('auth:sanctum')->controller(AdTypeController::class)->group(function (): void {
        Route::get('/ad-types', 'index');
        Route::get('/ad-types/{adType}', 'show');
        Route::post('/ad-types', 'store');
        Route::put('/ad-types/{adType}', 'update');
        Route::delete('/ad-types/{adType}', 'destroy');
    });

    // City
    Route::controller(CityController::class)->group(function (): void {
        Route::get('/cities', 'index');
        Route::get('/cities/{id}', 'show');
        Route::post('/cities', 'store')->middleware('auth:sanctum');
        Route::put('/cities/{city}', 'update')->middleware('auth:sanctum');
        Route::delete('/cities/{city}', 'destroy')->middleware('auth:sanctum');
    });

    // Quarter
    Route::controller(QuarterController::class)->group(function (): void {
        Route::get('/quarters', 'index');
        Route::get('/quarters/{id}', 'show');
        Route::post('/quarters', 'store')->middleware('auth:sanctum');
        Route::put('/quarters/{quarter}', 'update')->middleware('auth:sanctum');
        Route::delete('/quarters/{quarter}', 'destroy')->middleware('auth:sanctum');
    });

    // Agency
    Route::controller(AgencyController::class)->group(function (): void {
        Route::get('/agencies', 'index');
        Route::get('/agencies/{agency}', 'show');
        Route::post('/agencies', 'store')->middleware('auth:sanctum');
        Route::put('/agencies/{agency}', 'update')->middleware('auth:sanctum');
        Route::delete('/agencies/{agency}', 'destroy')->middleware('auth:sanctum');
    });

    // User
    Route::middleware('auth:sanctum')->controller(UserController::class)->group(function (): void {
        Route::get('/users', 'index');
        Route::get('/users/{id}', 'show');
        Route::post('/users', 'store');
        Route::put('/users/{user}', 'update');
        Route::delete('/users/{user}', 'destroy');
    });

    // --- RECOMMANDATIONS ---
    Route::middleware('auth:sanctum')->get('/recommendations', [RecommendationController::class, 'index']);

    // --- MES ANNONCES DÉBLOQUÉES ---
    Route::middleware('auth:sanctum')->get('/my/unlocked-ads', function () {
        $user = request()->user();
        $adIds = \App\Models\Payment::where('user_id', $user->id)
            ->where('type', \App\Enums\PaymentType::UNLOCK)
            ->where('status', \App\Enums\PaymentStatus::SUCCESS)
            ->pluck('ad_id');

        $ads = \App\Models\Ad::with('quarter.city', 'ad_type', 'media', 'user.agency', 'user.city', 'agency')
            ->whereIn('id', $adIds)
            ->latest()
            ->get();

        return \App\Http\Resources\AdResource::collection($ads);
    });

    // --- MES FAVORIS ---
    Route::middleware('auth:sanctum')->get('/my/favorites', [AdInteractionController::class, 'favorites']);

    // --- NOTIFICATIONS ---
    Route::middleware('auth:sanctum')->prefix('notifications')->controller(NotificationController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('/unread-count', 'unreadCount');
        Route::post('/read-all', 'markAllAsRead');
        Route::post('/{id}/read', 'markAsRead');
        Route::delete('/{id}', 'destroy');
    });

    // --- PROPERTY ATTRIBUTES (public) ---
    Route::get('/property-attributes', [NotificationController::class, 'propertyAttributes']);

    // --- PRIX DE DÉBLOCAGE ---
    Route::get('/payments/unlock-price', fn () => response()->json([
        'unlock_price' => (int) \App\Models\Setting::get('unlock_price', 500),
    ]));

    // --- PAIEMENTS ---
    Route::post('/payments/initialize/{ad}', [PaymentController::class, 'initialize'])
        ->middleware(['auth:sanctum', 'throttle:10,1']);
    Route::post('/payments/verify/{ad}', [PaymentController::class, 'verify'])
        ->middleware('auth:sanctum');
    Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
    Route::get('/payments/callback', [PaymentController::class, 'callback']);

    // --- ABONNEMENTS AGENCES ---
    Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans']);
    Route::middleware('auth:sanctum')->prefix('subscriptions')->group(function (): void {
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])
            ->middleware('throttle:5,1');
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::get('/history', [SubscriptionController::class, 'history']);
    });

    // --- REVIEWS ---
    Route::get('/ads/{ad}/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store'])
        ->middleware(['auth:sanctum', 'throttle:10,1']);

    //  Ads
    Route::prefix('ads')->controller(AdController::class)->middleware('optional.auth')->group(function (): void {
        Route::get('/', 'index');
        // Public nearby search by coordinates
        Route::get('/nearby', 'ads_nearby_public')->middleware('throttle:60,1');

        // Public search endpoint (must be before catch-all ID route)
        Route::get('/search', 'search')->name('ads.search')->middleware('throttle:60,1');
        Route::get('/autocomplete', 'autocomplete')->name('ads.autocomplete')->middleware('throttle:60,1');
        Route::get('/facets', 'facets')->name('ads.facets')->middleware('throttle:60,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            // Routes spécifiques AVANT les routes avec paramètres génériques
            Route::get('/{user}/nearby', 'ads_nearby_user');

            Route::post('', 'store');
            Route::put('/{ad}', 'update');
            Route::delete('/{id}', 'destroy');

            // Ad visibility and status management (Task 4 & 5)
            Route::post('/{ad}/toggle-visibility', 'toggleVisibility');
            Route::post('/{ad}/set-status', 'setStatus');
            Route::post('/{ad}/set-availability', 'setAvailability');
        });

        // Capture l'ID de l'annonce (doit être en dernier)
        Route::get('/{id}', 'show');
    });

    // --- INTERACTIONS (vues, favoris, impressions, partages, clics) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/ads/{ad}/view', [AdInteractionController::class, 'trackView'])
            ->middleware('throttle:120,1');
        Route::post('/ads/{ad}/favorite', [AdInteractionController::class, 'toggleFavorite'])
            ->middleware('throttle:30,1');
        Route::post('/ads/{ad}/impression', [AdInteractionController::class, 'trackImpression'])
            ->middleware('throttle:300,1');
        Route::post('/ads/{ad}/share', [AdInteractionController::class, 'trackShare'])
            ->middleware('throttle:30,1');
        Route::post('/ads/{ad}/contact-click', [AdInteractionController::class, 'trackContactClick'])
            ->middleware('throttle:30,1');
        Route::post('/ads/{ad}/phone-click', [AdInteractionController::class, 'trackPhoneClick'])
            ->middleware('throttle:30,1');
    });

    // --- ANALYTICS (dashboard bailleur/agence) ---
    Route::middleware('auth:sanctum')->prefix('my/ads')->group(function (): void {
        Route::get('/analytics', [AdAnalyticsController::class, 'overview']);
        Route::get('/{ad}/analytics', [AdAnalyticsController::class, 'show']);
    });
});
