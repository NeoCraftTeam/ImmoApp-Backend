<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Ad\AdSearchIsochroneController;
use App\Http\Controllers\Api\V1\Geo\DirectionsController;
use App\Http\Controllers\Api\V1\Geo\GeoFindOrCreateController;
use App\Http\Controllers\Api\V1\Geo\IsochroneController;
use Illuminate\Support\Facades\Route;

// Isochrones — zone accessible en X minutes depuis un point (ORS, cached 24h)
Route::get('/isochrones', IsochroneController::class)
    ->middleware(['optional.auth', 'throttle:30,1']);

// Isochrone ad search — ads reachable within N minutes from a point
Route::post('/search/isochrone', AdSearchIsochroneController::class)
    ->middleware(['optional.auth', 'throttle:20,1']);

// Directions — itinéraire A→B avec résumé distance/durée (ORS, cached 1h)
Route::get('/directions', DirectionsController::class)
    ->middleware(['optional.auth', 'throttle:60,1']);

// Géo find-or-create — bailleurs : créer une ville/quartier manquant à la volée
Route::middleware(['auth:sanctum', 'throttle:20,1'])
    ->controller(GeoFindOrCreateController::class)
    ->prefix('geo')
    ->group(function (): void {
        Route::post('/city', 'city');
        Route::post('/quarter', 'quarter');
    });
