<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PublicSurveyController;
use App\Http\Controllers\Api\V1\SurveyController;
use Illuminate\Support\Facades\Route;

// Public surveys (no auth required)
Route::prefix('public')->group(function (): void {
    Route::get('/surveys', [PublicSurveyController::class, 'index']);
    Route::get('/surveys/{survey:slug}', [PublicSurveyController::class, 'show']);
    Route::post('/surveys/{survey:slug}/respond', [PublicSurveyController::class, 'submit'])
        ->middleware('throttle:10,1');
});

// Admin/authenticated surveys
Route::get('/surveys/active', [SurveyController::class, 'active']);
Route::get('/surveys/{survey}', [SurveyController::class, 'show']);
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/surveys/{survey}/responses', [SurveyController::class, 'submitResponse'])
        ->middleware('throttle:10,1');
    Route::get('/surveys/{survey}/has-answered', [SurveyController::class, 'hasAnswered']);
});
