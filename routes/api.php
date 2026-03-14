<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdAnalyticsController;
use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\AdInteractionController;
use App\Http\Controllers\Api\V1\AdReportController;
use App\Http\Controllers\Api\V1\AdTypeController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\ClerkWebhookController;
use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PropertyAttributeController;
use App\Http\Controllers\Api\V1\PublicSurveyController;
use App\Http\Controllers\Api\V1\PwaController;
use App\Http\Controllers\Api\V1\QuarterController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SurveyController;
use App\Http\Controllers\Api\V1\TourController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ViewingAvailabilityController;
use App\Http\Controllers\Api\V1\ViewingReservationController;
use Illuminate\Support\Facades\Route;

// Health check endpoint (used by CI/CD smoke tests)
Route::get('/health', fn () => response()->json(['status' => 'ok']));

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

        Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('throttle:5,10')
            ->name('api.verification.verify');

        Route::post('verify-email-otp', [AuthController::class, 'verifyEmailOtp'])
            ->middleware('throttle:5,1');

        // Password Reset
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,10');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,10');

        // Clerk JWT → Sanctum token exchange
        Route::post('clerk/exchange', [AuthController::class, 'clerkExchange'])->middleware('throttle:10,1');
        Route::post('clerk/verify-otp', [AuthController::class, 'verifyClerkOtp'])->middleware('throttle:5,1');
        Route::post('clerk/complete-profile', [AuthController::class, 'completeClerkProfile'])->middleware('throttle:5,1');

        // OAuth Social Authentication
        Route::prefix('oauth')->controller(SocialAuthController::class)->group(function (): void {
            // Public OAuth endpoints
            Route::post('{provider}', 'authenticate')
                ->middleware('throttle:10,1')
                ->where('provider', 'google|facebook|apple');

            Route::get('{provider}/redirect', 'redirect')
                ->middleware('throttle:10,1')
                ->where('provider', 'google|facebook|apple');

            Route::get('{provider}/callback', 'callback')
                ->middleware('throttle:10,1')
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
            Route::post('update-password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,10');
            Route::post('onboarding-complete', [AuthController::class, 'completeOnboarding']);
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
    Route::middleware('auth:sanctum')->get('/my/unlocked-ads', [UserController::class, 'unlockedAds']);

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
    Route::get('/property-attributes', [PropertyAttributeController::class, 'index']);

    // --- VISIT TRACKING (anonymous) ---
    Route::post('/track/visit', [\App\Http\Controllers\Api\V1\VisitTrackingController::class, 'store'])
        ->middleware('throttle:60,1');

    // --- PUBLIC LANDING STATS ---
    Route::get('/stats/landing', fn () => response()->json([
        'ads_count' => \App\Models\Ad::query()->publiclyListed()->where('is_visible', true)->count(),
        'cities_count' => \App\Models\City::query()->count(),
        'users_count' => \App\Models\User::query()->count(),
    ]))->middleware('throttle:30,1');

    // --- CLERK WEBHOOKS ---
    Route::post('/clerk/webhook', [ClerkWebhookController::class, 'handle'])
        ->middleware('throttle:60,1');

    // --- WEBHOOKS PAIEMENT (pas d'auth, signature validée dans le controller) ---
    Route::post('/webhooks/flutterwave', [PaymentController::class, 'flutterwaveWebhook'])
        ->middleware('throttle:120,1');

    // --- TOUR 3D (public read, protected write) ---
    Route::get('/ads/{ad}/tour', [TourController::class, 'show']);
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/ads/{ad}/tour/scenes', [TourController::class, 'uploadScenes'])
            ->middleware('throttle:10,1');
        Route::match(['patch', 'post'], '/ads/{ad}/tour/scenes/{sceneId}/hotspots', [TourController::class, 'updateHotspots']);
        Route::delete('/ads/{ad}/tour', [TourController::class, 'destroy']);
    });

    // --- PAIEMENTS FLUTTERWAVE ---
    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::post('/payments/initialize/{ad}', [CreditController::class, 'unlock'])
            ->middleware('throttle:30,1');
        Route::post('/payments/initiate_payment', [PaymentController::class, 'flutterwaveInitiate'])
            ->middleware('throttle:5,1');
        Route::post('/payments/verify_payment', [PaymentController::class, 'flutterwaveVerify'])
            ->middleware('throttle:30,1');
        Route::post('/payments/cancel_payment', [PaymentController::class, 'flutterwaveCancel'])
            ->middleware('throttle:10,1');
        Route::get('/payments/history', [PaymentController::class, 'history'])
            ->middleware('throttle:60,1');
    });

    // --- ABONNEMENTS AGENCES ---
    Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans']);
    Route::middleware('auth:sanctum')->prefix('subscriptions')->group(function (): void {
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])
            ->middleware('throttle:5,1');
        Route::post('/cancel', [SubscriptionController::class, 'cancel'])
            ->middleware('throttle:5,1');
        Route::get('/history', [SubscriptionController::class, 'history']);
    });

    // --- CRÉDITS / POINTS ---
    Route::get('/credits/packages', [CreditController::class, 'packages']);
    Route::middleware('auth:sanctum')->prefix('credits')->group(function (): void {
        Route::get('/balance', [CreditController::class, 'balance']);
        Route::post('/purchase/{package}', [CreditController::class, 'purchase'])
            ->middleware('throttle:10,1');
        Route::post('/verify-purchase', [CreditController::class, 'verifyPurchase'])
            ->middleware('throttle:30,1');
    });

    // --- FACTURES ---
    Route::middleware('auth:sanctum')->prefix('invoices')->group(function (): void {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/{invoice}', [InvoiceController::class, 'show']);
        Route::get('/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
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
    // View & impression tracking: optional auth so guests can also be tracked
    Route::middleware('optional.auth')->group(function (): void {
        Route::post('/ads/{ad}/view', [AdInteractionController::class, 'trackView'])
            ->middleware('throttle:120,1');
        Route::post('/ads/{ad}/impression', [AdInteractionController::class, 'trackImpression'])
            ->middleware('throttle:300,1');
    });

    // Actions requiring authentication
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/ads/{ad}/favorite', [AdInteractionController::class, 'toggleFavorite'])
            ->middleware('throttle:30,1');
        Route::post('/ads/{ad}/share', [AdInteractionController::class, 'trackShare'])
            ->middleware('throttle:30,1');
        Route::post('/ads/{ad}/contact-click', [AdInteractionController::class, 'trackContactClick'])
            ->middleware('throttle:30,1');
        Route::post('/ads/{ad}/phone-click', [AdInteractionController::class, 'trackPhoneClick'])
            ->middleware('throttle:30,1');
        Route::post('/ads/{ad}/reports', [AdReportController::class, 'store'])
            ->middleware('throttle:30,1');
    });

    // --- ANALYTICS (dashboard bailleur/agence) ---
    Route::middleware('auth:sanctum')->prefix('my/ads')->group(function (): void {
        Route::get('/analytics', [AdAnalyticsController::class, 'overview']);
        Route::get('/{ad}/analytics', [AdAnalyticsController::class, 'show']);
    });

    // --- VIEWING AVAILABILITY (landlord) ---
    Route::middleware('auth:sanctum')->prefix('ads/{ad}')->group(function (): void {
        Route::get('/availability', [ViewingAvailabilityController::class, 'index']);
        Route::post('/availability', [ViewingAvailabilityController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::put('/availability/{schedule}', [ViewingAvailabilityController::class, 'update'])
            ->middleware('throttle:20,1');
        Route::delete('/availability/{schedule}', [ViewingAvailabilityController::class, 'destroy'])
            ->middleware('throttle:20,1');
        Route::get('/availability/calendar', [ViewingAvailabilityController::class, 'calendar']);
        Route::get('/reservations', [ViewingAvailabilityController::class, 'reservations']);
    });

    // --- VIEWING SLOTS (public) ---
    Route::get('/ads/{ad}/slots', [ViewingReservationController::class, 'slots'])
        ->middleware('throttle:60,1');

    // --- TENTATIVE RESERVATIONS (client) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/ads/{ad}/reservations', [ViewingReservationController::class, 'store'])
            ->middleware('throttle:5,1');
        Route::get('/my/reservations', [ViewingReservationController::class, 'myReservations']);
        Route::delete('/reservations/{reservation}', [ViewingReservationController::class, 'cancel'])
            ->middleware('throttle:20,1');
    });

    // --- PUBLIC SURVEYS (no auth required) ---
    Route::prefix('public')->group(function (): void {
        Route::get('/surveys', [PublicSurveyController::class, 'index']);
        Route::get('/surveys/{survey:slug}', [PublicSurveyController::class, 'show']);
        Route::post('/surveys/{survey:slug}/respond', [PublicSurveyController::class, 'submit'])
            ->middleware('throttle:10,1');
    });

    // --- SURVEYS ---
    Route::get('/surveys/active', [SurveyController::class, 'active']);
    Route::get('/surveys/{survey}', [SurveyController::class, 'show']);
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/surveys/{survey}/responses', [SurveyController::class, 'submitResponse'])
            ->middleware('throttle:10,1');
        Route::get('/surveys/{survey}/has-answered', [SurveyController::class, 'hasAnswered']);
    });

    // --- PWA (Push Subscriptions & Session Validation) ---
    Route::prefix('pwa')->middleware('web')->group(function (): void {
        Route::middleware('auth:web,sanctum')->group(function (): void {
            Route::post('/push/subscribe', [PwaController::class, 'subscribe']);
            Route::post('/push/unsubscribe', [PwaController::class, 'unsubscribe']);
        });
        Route::get('/session/validate', [PwaController::class, 'validateSession']);
    });
});
