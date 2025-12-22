<?php

use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\AdTypeController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\QuarterController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

// Prefix routes
Route::prefix('v1')->group(function () {

    //  Auth
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        // Public routes with throttling(rate limiting)
        Route::post('registerCustomer', [AuthController::class, 'registerCustomer'])
            ->middleware('throttle:5,1'); // 5 attempts per minute

        Route::post('registerAgent', [AuthController::class, 'registerAgent'])
            ->middleware('throttle:5,1'); //  5 attempts per minute

        Route::post('registerAd', [AuthController::class, 'registerAdmin'])
            ->middleware('throttle:5,1'); //  5 attempts per minute

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1'); //  5 attempts per minute

        Route::post('resend-verification', [AuthController::class, 'resendVerificationEmail'])
            ->middleware('throttle:2,5'); // 2 attempts every 5 minutes

        Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('api.verification.verify');

        // Routes protégées
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('email/resend', [AuthController::class, 'resendVerificationEmail'])->middleware('auth:sanctum');
        });
    });

    // adType
    Route::middleware('auth:sanctum')->controller(AdTypeController::class)->group(function () {
        Route::get('/ad-types', 'index');
        Route::get('/ad-types/{adType}', 'show');
        Route::post('/ad-types', 'store');
        Route::put('/ad-types/{adType}', 'update');
        Route::delete('/ad-types/{adType}', 'destroy');
    });

    // City
    Route::controller(CityController::class)->group(function () {
        Route::get('/cities', 'index');
        Route::get('/cities/{id}', 'show');
        Route::post('/cities', 'store')->middleware('auth:sanctum');
        Route::put('/cities/{city}', 'update')->middleware('auth:sanctum');
        Route::delete('/cities/{city}', 'destroy')->middleware('auth:sanctum');
    });

    // Quarter
    Route::controller(QuarterController::class)->group(function () {
        Route::get('/quarters', 'index');
        Route::get('/quarters/{id}', 'show');
        Route::post('/quarters', 'store')->middleware('auth:sanctum');
        Route::put('/quarters/{quarter}', 'update')->middleware('auth:sanctum');
        Route::delete('/quarters/{quarter}', 'destroy')->middleware('auth:sanctum');
    });

    // Agency
    Route::controller(AgencyController::class)->group(function () {
        Route::get('/agencies', 'index');
        Route::get('/agencies/{agency}', 'show');
        Route::post('/agencies', 'store')->middleware('auth:sanctum');
        Route::put('/agencies/{agency}', 'update')->middleware('auth:sanctum');
        Route::delete('/agencies/{agency}', 'destroy')->middleware('auth:sanctum');
    });

    // User
    Route::middleware('auth:sanctum')->controller(UserController::class)->group(function () {
        Route::get('/users', 'index');
        Route::get('/users/{id}', 'show');
        Route::post('/users', 'store');
        Route::put('/users/{user}', 'update');
        Route::delete('/users/{user}', 'destroy');
    });

    // Payments
    Route::middleware('auth:sanctum')->prefix('payments')->controller(PaymentController::class)->group(function () {
        Route::post('/unlock', 'unlockAd');
    });

    //  Ads
    Route::prefix('ads')->controller(AdController::class)->group(function () {
        Route::get('/', 'index');
        // Public nearby search by coordinates
        Route::get('/nearby', 'ads_nearby_public');

        // Public search endpoint (must be before catch-all ID route)
        Route::get('/search', 'search')->name('ads.search');
        Route::get('/autocomplete', 'autocomplete')->name('ads.autocomplete');
        Route::get('/facets', 'facets')->name('ads.facets');

        Route::middleware('auth:sanctum')->group(function () {
            // Routes spécifiques AVANT les routes avec paramètres génériques
            Route::get('/{user}/nearby', 'ads_nearby_user')->whereNumber('user');

            Route::post('', 'store');
            Route::put('/{ad}', 'update');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

        // Cette route DOIT être en dernier pour ne pas capturer d'autres patterns
        Route::get('/{id}', 'show')->whereNumber('id');
    });
});
