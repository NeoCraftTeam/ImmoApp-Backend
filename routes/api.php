<?php

use App\Http\Controllers\Api\V1\welcomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Prefix routes 
Route::prefix('v1')->group(function () {
    
    //welcome
    Route::controller(welcomeController::class)->group(function () {
        Route::get('/users', 'index');
        Route::get('/users/{id}', 'show');
    });


});
