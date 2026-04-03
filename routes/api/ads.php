<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdAiController;
use App\Http\Controllers\Api\V1\AdAnalyticsController;
use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\AdGeoController;
use App\Http\Controllers\Api\V1\AdInteractionController;
use App\Http\Controllers\Api\V1\AdPdfController;
use App\Http\Controllers\Api\V1\AdReportController;
use App\Http\Controllers\Api\V1\AdSearchController;
use App\Http\Controllers\Api\V1\AdStatusController;
use App\Http\Controllers\Api\V1\KeyScoreController;
use App\Http\Controllers\Api\V1\MyAdsController;
use App\Http\Controllers\Api\V1\NeighborhoodScorecardController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\TourController;
use App\Models\Review;
use Illuminate\Support\Facades\Route;

// Ad CRUD & public search
Route::prefix('ads')->middleware('optional.auth')->group(function (): void {
    Route::get('/', [AdController::class, 'index']);

    // Geo proximity (public)
    Route::get('/nearby', [AdGeoController::class, 'ads_nearby_public'])->middleware('throttle:60,1');

    // Search & facets
    Route::get('/search', [AdSearchController::class, 'search'])->name('ads.search')->middleware('throttle:60,1');
    Route::get('/autocomplete', [AdSearchController::class, 'autocomplete'])->name('ads.autocomplete')->middleware('throttle:60,1');
    Route::get('/facets', [AdSearchController::class, 'facets'])->name('ads.facets')->middleware('throttle:60,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/ai/enhance-description', AdAiController::class)
            ->middleware('throttle:10,1');

        // Geo proximity — authenticated
        Route::get('/{user}/nearby', [AdGeoController::class, 'ads_nearby_user']);
    });

    // CRUD write + status — owner/admin only (belt-and-suspenders with AdPolicy)
    Route::middleware(['auth:sanctum', 'owner.role', 'panel.role:owner', 'token.role:agent'])->group(function (): void {
        Route::post('', [AdController::class, 'store']);
        Route::put('/{ad}', [AdController::class, 'update']);
        Route::delete('/{id}', [AdController::class, 'destroy']);

        Route::post('/{ad}/toggle-visibility', [AdStatusController::class, 'toggleVisibility']);
        Route::post('/{ad}/set-status', [AdStatusController::class, 'setStatus']);
        Route::post('/{ad}/set-availability', [AdStatusController::class, 'setAvailability']);
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
    Route::post('/ads/{ad}/view', [AdInteractionController::class, 'trackView'])
        ->middleware('throttle:120,1');
    Route::post('/ads/{ad}/impression', [AdInteractionController::class, 'trackImpression'])
        ->middleware('throttle:300,1');
});

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
Route::middleware(['auth:sanctum', 'owner.role', 'panel.role:owner'])->prefix('my/ads')->group(function (): void {
    Route::get('/', [MyAdsController::class, 'index']);
    Route::get('/analytics', [AdAnalyticsController::class, 'overview']);
    Route::get('/{ad}/analytics', [AdAnalyticsController::class, 'show']);
});

// KeyScore
Route::get('/ads/{ad}/keyscore', [KeyScoreController::class, 'show'])
    ->middleware('throttle:60,1');

// Neighborhood scorecard (OSM Overpass — cached 7 days)
Route::get('/ads/{ad}/neighborhood-scorecard', NeighborhoodScorecardController::class)
    ->middleware(['optional.auth', 'throttle:30,1']);

// PDF export — public for available ads, owner/admin for others
Route::get('/ads/{ad}/pdf', [AdPdfController::class, 'download'])
    ->middleware(['optional.auth', 'throttle:30,1'])
    ->name('ads.pdf');

// 3D Tour (public read, protected write)
Route::get('/ads/{ad}/tour', [TourController::class, 'show'])->middleware('optional.auth');
Route::middleware(['auth:sanctum', 'owner.role', 'panel.role:owner'])->group(function (): void {
    Route::post('/ads/{ad}/tour/scenes', [TourController::class, 'uploadScenes'])
        ->middleware('throttle:10,1');
    Route::match(['patch', 'post'], '/ads/{ad}/tour/scenes/{sceneId}/hotspots', [TourController::class, 'updateHotspots']);
    Route::delete('/ads/{ad}/tour', [TourController::class, 'destroy']);
});
