<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdInteractionController;
use App\Http\Controllers\Api\V1\AdTypeController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\ClerkWebhookController;
use App\Http\Controllers\Api\V1\GdprController;
use App\Http\Controllers\Api\V1\LeaseContractController;
use App\Http\Controllers\Api\V1\MyReviewsController;
use App\Http\Controllers\Api\V1\NaturalSearchController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PriceHeatmapController;
use App\Http\Controllers\Api\V1\PropertyAttributeController;
use App\Http\Controllers\Api\V1\PwaController;
use App\Http\Controllers\Api\V1\QuarterController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\RentEstimatorController;
use App\Http\Controllers\Api\V1\SearchAlertController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VisitTrackingController;
use App\Http\Resources\TestimonialResource;
use App\Models\Ad;
use App\Models\AdType;
use App\Models\Agency;
use App\Models\City;
use App\Models\Quarter;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Health check endpoint (used by CI/CD smoke tests)
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Prefix routes
Route::prefix('v1')->group(function (): void {

    // Domain route files
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/ads.php';
    require __DIR__.'/api/payments.php';
    require __DIR__.'/api/viewings.php';
    require __DIR__.'/api/surveys.php';

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
    Route::middleware('auth:sanctum')->controller(UserController::class)->group(function (): void {
        Route::get('/users', 'index')->can('viewAny', User::class);
        Route::get('/users/{id}', 'show');
        Route::post('/users', 'store')->can('create', User::class);
        Route::put('/users/{user}', 'update');
        Route::delete('/users/{user}', 'destroy');
    });

    // --- RECOMMENDATIONS ---
    Route::middleware('optional.auth')->get('/recommendations', [RecommendationController::class, 'index']);

    // --- MY UNLOCKED ADS ---
    Route::middleware('auth:sanctum')->get('/my/unlocked-ads', [UserController::class, 'unlockedAds']);

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

    // --- PUBLIC LANDING STATS ---
    Route::get('/stats/landing', fn () => response()->json([
        'ads_count' => Ad::query()->publiclyListed()->where('is_visible', true)->count(),
        'cities_count' => City::query()->count(),
        'users_count' => User::query()->count(),
    ]))->middleware('throttle:30,1');

    // --- PUBLIC LANDING TESTIMONIALS ---
    Route::get('/stats/testimonials', function () {
        $reviews = Review::query()
            ->whereNotNull('comment')
            ->where('rating', '>=', 4)
            ->with(['user.city'])
            ->latest()
            ->limit(8)
            ->get();

        $averageRating = round(
            (float) (Review::query()->avg('rating') ?? 4.6),
            1
        );
        $totalCount = Review::query()->count();

        return response()->json([
            'data' => TestimonialResource::collection($reviews),
            'meta' => [
                'average_rating' => $averageRating,
                'total_count' => $totalCount,
            ],
        ]);
    })->middleware('throttle:30,1');

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
});
