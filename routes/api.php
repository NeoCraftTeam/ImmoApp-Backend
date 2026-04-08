<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdInteractionController;
use App\Http\Controllers\Api\V1\AdTypeController;
use App\Http\Controllers\Api\V1\BailleurFollowController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\BoostController;
use App\Http\Controllers\Api\V1\BulkAdController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\ClerkWebhookController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DuplicateAdController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\GdprController;
use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\LeaseContractController;
use App\Http\Controllers\Api\V1\LoginHistoryController;
use App\Http\Controllers\Api\V1\MyReviewsController;
use App\Http\Controllers\Api\V1\NaturalSearchController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\PriceHeatmapController;
use App\Http\Controllers\Api\V1\PropertyAttributeController;
use App\Http\Controllers\Api\V1\PwaController;
use App\Http\Controllers\Api\V1\QuarterController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\RentEstimatorController;
use App\Http\Controllers\Api\V1\SearchAlertController;
use App\Http\Controllers\Api\V1\SignatureController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TrustScoreController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VisitTrackingController;
use App\Models\AdType;
use App\Models\Agency;
use App\Models\City;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Public liveness probe — no auth, no infra details. Used by CI smoke tests and uptime monitors.
Route::get('/ping', fn () => response()->json(['status' => 'ok'], 200));

// Comprehensive health check — DB · Redis · Queue · Storage · Meilisearch · Flutterwave
// Auth: optional static bearer token via HEALTH_CHECK_TOKEN env var (see HealthCheckController).
// Use ?force=true to bypass the 30-second result cache.
Route::get('/health', HealthCheckController::class)
    ->middleware('throttle:30,1');

// Prefix routes
Route::prefix('v1')->group(function (): void {

    // Domain route files
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/ads.php';
    require __DIR__.'/api/payments.php';
    require __DIR__.'/api/viewings.php';
    require __DIR__.'/api/surveys.php';
    require __DIR__.'/api/geo.php';

    // --- AD TYPES ---
    Route::controller(AdTypeController::class)->group(function (): void {
        Route::get('/ad-types', 'index');
        Route::get('/ad-types/{adType}', 'show');
    });
    Route::middleware('auth:sanctum')->controller(AdTypeController::class)->group(function (): void {
        Route::post('/ad-types', 'store')->can('create', AdType::class);
        Route::put('/ad-types/{adType}', 'update')->can('update', 'adType');
        Route::delete('/ad-types/{adType}', 'destroy')->can('delete', 'adType');
    });

    // --- CITIES ---
    Route::controller(CityController::class)->group(function (): void {
        Route::get('/cities', 'index');
        Route::get('/cities/{id}', 'show');
        Route::post('/cities', 'store')->middleware('auth:sanctum')->can('create', City::class);
        Route::put('/cities/{city}', 'update')->middleware('auth:sanctum')->can('update', 'city');
        Route::delete('/cities/{city}', 'destroy')->middleware('auth:sanctum')->can('delete', 'city');
    });

    // --- QUARTERS ---
    Route::controller(QuarterController::class)->group(function (): void {
        Route::get('/quarters', 'index');
        Route::get('/quarters/{id}', 'show');
        Route::post('/quarters', 'store')->middleware('auth:sanctum')->can('create', Quarter::class);
        Route::put('/quarters/{quarter}', 'update')->middleware('auth:sanctum')->can('update', 'quarter');
        Route::delete('/quarters/{quarter}', 'destroy')->middleware('auth:sanctum')->can('delete', 'quarter');
    });

    // --- AGENCIES ---
    Route::controller(AgencyController::class)->group(function (): void {
        Route::get('/agencies', 'index');
        Route::get('/agencies/{agency}', 'show');
        Route::post('/agencies', 'store')->middleware('auth:sanctum')->can('create', Agency::class);
        Route::put('/agencies/{agency}', 'update')->middleware('auth:sanctum')->can('update', 'agency');
        Route::delete('/agencies/{agency}', 'destroy')->middleware('auth:sanctum')->can('delete', 'agency');
    });

    // --- USERS ---
    Route::get('/users/{identifier}/public-profile', [UserController::class, 'publicProfile'])
        ->middleware('throttle:60,1');
    Route::middleware('auth:sanctum')->controller(UserController::class)->group(function (): void {
        Route::get('/users', 'index')->can('viewAny', User::class);
        Route::get('/users/{id}', 'show');
        Route::post('/users', 'store')->can('create', User::class);
        Route::put('/users/{user}', 'update');
        Route::delete('/users/{user}', 'destroy');
    });

    // --- BAILLEUR FOLLOW ---
    // Status: optional auth — guests get following=false, authenticated users get their real state.
    Route::get('/bailleurs/{username}/follow', [BailleurFollowController::class, 'status'])
        ->middleware(['optional.auth', 'throttle:60,1']);
    // Toggle: requires auth.
    Route::post('/bailleurs/{username}/follow', [BailleurFollowController::class, 'toggle'])
        ->middleware(['auth:sanctum', 'throttle:30,1']);

    // --- RECOMMENDATIONS ---
    Route::middleware('optional.auth')->get('/recommendations', [RecommendationController::class, 'index']);

    // --- MY UNLOCKED ADS ---
    Route::middleware('auth:sanctum')->get('/my/unlocked-ads', [UserController::class, 'unlockedAds']);

    // --- TRUST SCORE ---
    Route::get('/users/{user}/trust-score', [TrustScoreController::class, 'show'])
        ->middleware('throttle:60,1');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/trust-score', [TrustScoreController::class, 'me']);
        Route::post('/my/trust-score/consent', [TrustScoreController::class, 'consent'])
            ->middleware('throttle:10,1');
    });

    // --- GDPR Data Export & Account Deletion ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/data-export', [GdprController::class, 'export'])
            ->middleware('throttle:5,1');
        Route::delete('/my/account', [GdprController::class, 'deleteAccount'])
            ->middleware('throttle:3,1');
    });

    // --- MY FAVORITES ---
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
    Route::post('/track/visit', [VisitTrackingController::class, 'store'])
        ->middleware('throttle:60,1');

    // --- PUBLIC STATS (W37: extracted from inline closures to StatsController) ---
    Route::controller(StatsController::class)->middleware('throttle:30,1')->group(function (): void {
        Route::get('/stats/landing', 'landing')->name('stats.landing');
        Route::get('/stats/testimonials', 'testimonials')->name('stats.testimonials');
    });

    // --- CLERK WEBHOOKS ---
    Route::post('/clerk/webhook', [ClerkWebhookController::class, 'handle'])
        ->middleware('throttle:60,1');

    // --- LEASE CONTRACTS (landlord) ---
    Route::middleware('auth:sanctum')->prefix('my/lease-contracts')->controller(LeaseContractController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/ai/enhance-conditions', 'enhanceConditions')
            ->middleware('throttle:10,1');
        Route::get('/{leaseContract}', 'show')->name('lease-contracts.show');
        Route::put('/{leaseContract}', 'update')->name('lease-contracts.update');
        Route::post('/{ad}/generate', 'store')->name('lease-contracts.generate');
        Route::get('/{leaseContract}/download', 'download')->name('lease-contracts.download');
    });

    // --- MY REVIEWS (landlord — reviews on my ads) ---
    Route::middleware('auth:sanctum')->get('/my/reviews', [MyReviewsController::class, 'index']);

    // --- PWA (Push Subscriptions & Session Validation) ---
    Route::prefix('pwa')->middleware('web')->group(function (): void {
        Route::middleware('auth:web,sanctum')->group(function (): void {
            Route::post('/push/subscribe', [PwaController::class, 'subscribe']);
            Route::post('/push/unsubscribe', [PwaController::class, 'unsubscribe']);
        });
        Route::get('/session/validate', [PwaController::class, 'validateSession']);
    });

    // --- Push (SPA / Bearer token — for Next.js frontend) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/push/subscribe', [PwaController::class, 'subscribe']);
        Route::post('/push/unsubscribe', [PwaController::class, 'unsubscribe']);
    });

    // --- SEARCH ALERTS ---
    Route::middleware('auth:sanctum')->prefix('search-alerts')->group(function (): void {
        Route::get('/', [SearchAlertController::class, 'index']);
        Route::post('/', [SearchAlertController::class, 'store'])->middleware('throttle:20,1');
        Route::put('/{searchAlert}', [SearchAlertController::class, 'update']);
        Route::delete('/{searchAlert}', [SearchAlertController::class, 'destroy']);
    });

    // --- RENT ESTIMATOR (public) ---
    Route::get('/rent-estimate', [RentEstimatorController::class, 'estimate'])
        ->middleware('throttle:30,1');

    // --- PRICE HEATMAP (public) ---
    Route::get('/price-heatmap', [PriceHeatmapController::class, 'index'])
        ->middleware('throttle:30,1');

    // --- NATURAL LANGUAGE SEARCH ---
    Route::post('/search/parse', [NaturalSearchController::class, 'parse'])
        ->middleware('throttle:30,1');

    // --- NEWSLETTER ---
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
        ->middleware('throttle:5,10');
    Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe']);

    // --- BOOST (owner) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/boost-plans', [BoostController::class, 'plans']);
        Route::get('/my/ads/{ad}/boost-status', [BoostController::class, 'status']);
        Route::post('/my/ads/{ad}/boost', [BoostController::class, 'boost'])->middleware('throttle:10,1');
        Route::delete('/my/ads/{ad}/boost', [BoostController::class, 'unboost']);
        Route::post('/my/ads/{ad}/duplicate', [DuplicateAdController::class, 'store']);
        Route::put('/my/ads/bulk-update', [BulkAdController::class, 'bulkUpdate']);
        Route::post('/my/ads/bulk-delete', [BulkAdController::class, 'bulkDelete']);
    });

    // --- TENANTS (owner) ---
    Route::middleware('auth:sanctum')->prefix('my/tenants')->controller(TenantController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware('throttle:30,1');
        Route::get('/{tenant}', 'show');
        Route::put('/{tenant}', 'update');
        Route::delete('/{tenant}', 'destroy');
    });

    // --- EXPENSES (owner, per property) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/ads/{ad}/expenses', [ExpenseController::class, 'index']);
        Route::post('/my/ads/{ad}/expenses', [ExpenseController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/my/ads/{ad}/profit-loss', [ExpenseController::class, 'profitLoss']);
        Route::delete('/my/expenses/{expense}', [ExpenseController::class, 'destroy']);
    });

    // --- DOCUMENTS (owner, per property) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/ads/{ad}/documents', [DocumentController::class, 'index']);
        Route::post('/my/ads/{ad}/documents', [DocumentController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/my/documents/{document}/download', [DocumentController::class, 'download']);
        Route::delete('/my/documents/{document}', [DocumentController::class, 'destroy']);
    });

    // --- NOTIFICATION PREFERENCES (owner) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('/my/notification-preferences', [NotificationPreferenceController::class, 'update']);
    });

    // --- LOGIN HISTORY (owner) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/login-history', [LoginHistoryController::class, 'index']);
        Route::delete('/my/login-history', [LoginHistoryController::class, 'destroy']);
    });

    // --- TEAM MANAGEMENT (agency owners) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/team', [TeamController::class, 'index']);
        Route::post('/my/team/invite', [TeamController::class, 'invite'])->middleware('throttle:10,1');
        Route::post('/my/team/invitations/{token}/accept', [TeamController::class, 'accept']);
        Route::delete('/my/team/invitations/{teamInvitation}', [TeamController::class, 'destroy']);
        Route::delete('/my/team/members/{user}', [TeamController::class, 'removeMember']);
    });

    // --- E-SIGNATURE (owner) ---
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/my/lease-contracts/{leaseContract}/signatures', [SignatureController::class, 'index']);
        Route::post('/my/lease-contracts/{leaseContract}/signatures', [SignatureController::class, 'store'])->middleware('throttle:10,1');
    });

    // --- E-SIGNATURE (public — no auth required) ---
    Route::get('/signatures/{token}', [SignatureController::class, 'show']);
    Route::post('/signatures/{token}/sign', [SignatureController::class, 'sign'])->middleware('throttle:10,1');
    Route::post('/signatures/{token}/decline', [SignatureController::class, 'decline'])->middleware('throttle:10,1');
});
