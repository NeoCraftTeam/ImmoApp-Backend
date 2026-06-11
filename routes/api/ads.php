<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Ad\AdAiController;
use App\Http\Controllers\Api\V1\Ad\AdAnalyticsController;
use App\Http\Controllers\Api\V1\Ad\AdController;
use App\Http\Controllers\Api\V1\Ad\AdDraftEditController;
use App\Http\Controllers\Api\V1\Ad\AdGeoController;
use App\Http\Controllers\Api\V1\Ad\AdInteractionController;
use App\Http\Controllers\Api\V1\Ad\AdPdfController;
use App\Http\Controllers\Api\V1\Ad\AdRankEstimateController;
use App\Http\Controllers\Api\V1\Ad\AdReportController;
use App\Http\Controllers\Api\V1\Ad\AdSearchController;
use App\Http\Controllers\Api\V1\Ad\AdSimilarController;
use App\Http\Controllers\Api\V1\Ad\AdStatusController;
use App\Http\Controllers\Api\V1\Ad\BulkAdController;
use App\Http\Controllers\Api\V1\Ad\MyAdsController;
use App\Http\Controllers\Api\V1\Geo\NeighborhoodScorecardController;
use App\Http\Controllers\Api\V1\PrescreeningController;
use App\Http\Controllers\Api\V1\QrCodeController;
use App\Http\Controllers\Api\V1\TourController;
use App\Http\Controllers\Api\V1\User\KeyScoreController;
use App\Http\Controllers\Api\V1\User\ReviewController;
use App\Models\Review;
use Illuminate\Support\Facades\Route;

// Ad CRUD & public search
Route::prefix('ads')->middleware('optional.auth')->group(function (): void {
    Route::get('/', [AdController::class, 'index']);
    // /feed is the home page firehose for both guests and logged-in users.
    // 5min CDN cache covers guests entirely (Cloudflare absorbs the load and
    // origin only sees one request every 5min per cursor). Authenticated users
    // still hit the app (CdnCache short-circuits when $request->user() exists).
    Route::get('/feed', [AdController::class, 'feed'])->middleware('cdn.cache:300');

    // Geo proximity (public)
    Route::get('/nearby', [AdGeoController::class, 'ads_nearby_public'])->middleware('throttle:60,1');

    // Search & facets
    Route::get('/search', [AdSearchController::class, 'search'])->name('ads.search')->middleware('throttle:60,1');
    Route::get('/autocomplete', [AdSearchController::class, 'autocomplete'])->name('ads.autocomplete')->middleware('throttle:60,1');
    Route::get('/facets', [AdSearchController::class, 'facets'])->name('ads.facets')->middleware('throttle:60,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/ai/enhance-description', AdAiController::class)
            ->middleware('throttle:10,1');
        Route::post('/ai/generate-from-attributes', [AdAiController::class, 'generateFromAttributes'])
            ->middleware('throttle:10,1');
        Route::post('/ai/enhance-title', [AdAiController::class, 'enhanceTitle'])
            ->middleware('throttle:20,1');
        Route::post('/ai/stream-enhance', [AdAiController::class, 'stream'])
            ->middleware('throttle:10,1');

        // Geo proximity — authenticated
        Route::get('/{user}/nearby', [AdGeoController::class, 'ads_nearby_user']);
    });

    // Owner-panel write + status — AGENT only (admins use Filament; AdPolicy on update/delete below)
    Route::middleware(['auth:sanctum', 'owner.role', 'panel.role:owner', 'token.role:agent'])->group(function (): void {
        Route::post('', [AdController::class, 'store']);

        Route::post('/{ad}/toggle-visibility', [AdStatusController::class, 'toggleVisibility']);
        Route::post('/{ad}/set-status', [AdStatusController::class, 'setStatus']);
        Route::post('/{ad}/publish', [AdStatusController::class, 'publish'])->middleware('throttle:20,1');
        Route::post('/{ad}/set-availability', [AdStatusController::class, 'setAvailability']);
        Route::patch('/{ad}/autosave', [AdStatusController::class, 'autosave'])->middleware('throttle:60,1');

        // Pending-edit draft (server-side draft for modifying live ads)
        Route::patch('/{ad}/edit-draft', [AdDraftEditController::class, 'save'])->middleware('throttle:60,1');
        Route::post('/{ad}/edit-draft/apply', [AdDraftEditController::class, 'apply'])->middleware('throttle:20,1');
        Route::delete('/{ad}/edit-draft', [AdDraftEditController::class, 'discard'])->middleware('throttle:20,1');
    });

    // Bulk operations — registered before /{id} catch-alls to prevent wildcard shadowing
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::patch('/bulk/status', [BulkAdController::class, 'bulkUpdate']);
        Route::delete('/bulk', [BulkAdController::class, 'bulkDelete']);
    });

    // Ad update/delete — AdPolicy: admin (any ad) or agent (own ad); not owner-panel scoped
    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::put('/{ad}', [AdController::class, 'update']);
        Route::delete('/{id}', [AdController::class, 'destroy']);
    });

    // Must be last — captures {id}
    Route::get('/{id}', [AdController::class, 'show']);
});

// Reviews on ads
Route::get('/ads/{ad}/reviews', [ReviewController::class, 'index']);
Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:10,1'])
    ->can('create', Review::class);

// Owner response to a review
Route::post('/reviews/{review}/respond', [ReviewController::class, 'respond'])
    ->middleware(['auth:sanctum', 'throttle:10,1']);

// Reports on ads
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/ads/{ad}/reports', [AdReportController::class, 'store'])
        ->middleware('throttle:30,1');
});

// Interactions (views, favorites, impressions, shares, clicks)
Route::middleware('optional.auth')->group(function (): void {
    // Frontend sends the slug (from URL), so bind by slug column.
    Route::post('/ads/{ad:slug}/view', [AdInteractionController::class, 'trackView'])
        ->middleware('throttle:120,1');
    Route::post('/ads/{ad:slug}/impression', [AdInteractionController::class, 'trackImpression'])
        ->middleware('throttle:300,1');
});

// Recently viewed ads (authenticated only)
Route::middleware('auth:sanctum')->get('/my/recently-viewed', [AdInteractionController::class, 'recentlyViewed']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/ads/{ad}/favorite', [AdInteractionController::class, 'toggleFavorite'])
        ->middleware('throttle:30,1');
    Route::post('/ads/{ad}/share', [AdInteractionController::class, 'trackShare'])
        ->middleware('throttle:30,1');
    Route::post('/ads/{ad}/contact-click', [AdInteractionController::class, 'trackContactClick'])
        ->middleware('throttle:30,1');
    Route::post('/ads/{ad}/phone-click', [AdInteractionController::class, 'trackPhoneClick'])
        ->middleware('throttle:30,1');
});

// Analytics (landlord/agency dashboard)
Route::middleware(['auth:sanctum', 'owner.role', 'panel.role:owner', 'token.role:agent'])->prefix('my/ads')->group(function (): void {
    Route::get('/', [MyAdsController::class, 'index']);
    Route::get('/analytics', [AdAnalyticsController::class, 'overview']);
    Route::get('/{ad}/qr-code', [QrCodeController::class, 'adMeta'])->middleware('throttle:60,1');
    Route::get('/{ad}/qr-code/image', [QrCodeController::class, 'adQrImage'])->middleware('throttle:60,1');
    Route::get('/{ad}/placarde', [QrCodeController::class, 'adPlacarde'])->middleware('throttle:20,1');
    Route::get('/{ad}/analytics', [AdAnalyticsController::class, 'show']);

    // Item 11 — Prescreening questions (landlord manages)
    Route::patch('/{ad}/prescreening', [PrescreeningController::class, 'update'])
        ->middleware('throttle:30,1');
    Route::delete('/{ad}/prescreening', [PrescreeningController::class, 'destroy'])
        ->middleware('throttle:30,1');
    Route::get('/{ad}/placarde/preview', [QrCodeController::class, 'adPlacardePreview'])->middleware('throttle:20,1');
});

// KeyScore
Route::get('/ads/{ad}/keyscore', [KeyScoreController::class, 'show'])
    ->middleware('throttle:60,1');

// Rank estimate — owner/admin only (auth enforced in controller)
Route::get('/ads/{ad}/rank-estimate', AdRankEstimateController::class)
    ->middleware(['auth:sanctum', 'throttle:30,1']);

// Similar ads (public — roadmap 3.3)
Route::get('/ads/{ad}/similar', AdSimilarController::class)
    ->middleware(['optional.auth', 'throttle:60,1', 'cdn.cache:300']);

// Neighborhood scorecard (OSM Overpass — cached 7d server-side, also CDN-cached
// 1h at the edge for guests so concurrent visits don't hit Overpass at all).
Route::get('/ads/{ad}/neighborhood-scorecard', NeighborhoodScorecardController::class)
    ->middleware(['optional.auth', 'throttle:30,1', 'cdn.cache:3600']);

// PDF export — public for available ads, owner/admin for others
Route::get('/ads/{ad}/pdf', [AdPdfController::class, 'download'])
    ->middleware(['optional.auth', 'throttle:30,1'])
    ->name('ads.pdf');

// 3D Tour (public read, protected write)
Route::get('/ads/{ad}/tour', [TourController::class, 'show'])->middleware('optional.auth');
// OWASP A05 — align with the ads CRUD stack which adds `token.role:agent`
// for defense-in-depth (PAT carries the agent ability). Without this, a
// customer-context PAT bypassed the role check on tour mutation endpoints,
// even though `TourPolicy` still enforces ownership at the controller layer.
Route::middleware(['auth:sanctum', 'owner.role', 'panel.role:owner', 'token.role:agent'])->group(function (): void {
    Route::post('/ads/{ad}/tour/scenes', [TourController::class, 'uploadScenes'])
        ->middleware('throttle:10,1');
    Route::patch('/ads/{ad}/tour/scenes/{sceneId}/hotspots', [TourController::class, 'updateHotspots']);
    Route::delete('/ads/{ad}/tour', [TourController::class, 'destroy']);
});
