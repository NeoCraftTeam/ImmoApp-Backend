<?php

use App\Http\Controllers\API\V1\AdTypeController;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\API\V1\UserController;
use Illuminate\Support\Facades\Route;

// Prefix routes
Route::prefix('v1')->group(function () {

    //  Auth
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        // Public routes with throttling(rate limiting)
        Route::post('registerCustomer', [AuthController::class, 'registerCustomer'])
            ->middleware('throttle:3,1'); // 3 attempts per minute

        Route::post('registerAgent', [AuthController::class, 'registerAgent'])
            ->middleware('throttle:3,1'); //  3 attempts per minute

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1'); // 5  3 attempts per minute

        Route::post('resend-verification', [AuthController::class, 'resendVerificationEmail'])
            ->middleware('throttle:2,5'); // 2 attempts every 5 minutes

        // Routes protégées
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
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
    Route::middleware('auth:sanctum')->controller(CityController::class)->group(function () {
        Route::get('/cities', 'index');
        Route::get('/cities/{id}', 'show');
        Route::post('/cities', 'store');
        Route::put('/cities/{id}', 'update');
        Route::delete('/cities/{id}', 'destroy');
    });

// User
    Route::middleware('auth:sanctum')->controller(UserController::class)->group(function () {
        Route::get('/users', 'index');
        Route::get('/users/{id}', 'show');
        Route::post('/users', 'store');
        Route::put('/users/{user}', 'update');
        Route::delete('/users/{user}', 'destroy');
    });


});
