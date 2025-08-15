<?php

use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\API\V1\UserController;
use Illuminate\Support\Facades\Route;

// Prefix routes
Route::prefix('v1')->group(function () {


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
