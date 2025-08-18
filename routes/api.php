<?php

use App\Http\Controllers\API\V1\AdTypeController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\API\V1\UserController;
use Illuminate\Support\Facades\Route;

// Prefix routes
Route::prefix('v1')->group(function () {

    // adType
    Route::controller(AdTypeController::class)->group(function () {
        Route::get('/ad-types', 'index');
        Route::get('/ad-types/{id}', 'show');
        Route::post('/ad-types', 'store');
        Route::put('/ad-types/{id}', 'update');
        Route::delete('/ad-types/{id}', 'destroy');
    });


    // City
    Route::controller(CityController::class)->group(function () {
        Route::get('/cities', 'index');
        Route::get('/cities/{id}', 'show');
        Route::post('/cities', 'store');
        Route::put('/cities/{id}', 'update');
        Route::delete('/cities/{id}', 'destroy');
    });

    // User
    Route::controller(UserController::class)->group(function () {
        Route::get('/users', 'index');
        Route::get('/users/{id}', 'show');
        Route::post('/users', 'store');
        Route::put('/users/{id}', 'update');
        Route::delete('/users/{id}', 'destroy');
    });


});
