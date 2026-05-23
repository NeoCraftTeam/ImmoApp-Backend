<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CalendarExportController;
use App\Http\Controllers\Api\V1\ViewingAvailabilityController;
use App\Http\Controllers\Api\V1\ViewingReservationController;
use Illuminate\Support\Facades\Route;

// Viewing slots (public)
Route::get('/ads/{ad}/slots', [ViewingReservationController::class, 'slots'])
    ->middleware('throttle:60,1');

// Viewing availability (landlord)
Route::middleware('auth:sanctum')->prefix('ads/{ad}')->group(function (): void {
    Route::get('/availability', [ViewingAvailabilityController::class, 'index']);
    Route::post('/availability', [ViewingAvailabilityController::class, 'store'])
        ->middleware('throttle:20,1');
    Route::put('/availability/{schedule}', [ViewingAvailabilityController::class, 'update'])
        ->middleware('throttle:20,1');
    Route::delete('/availability/{schedule}', [ViewingAvailabilityController::class, 'destroy'])
        ->middleware('throttle:20,1');
    Route::get('/availability/calendar', [ViewingAvailabilityController::class, 'calendar']);
    Route::get('/reservations', [ViewingAvailabilityController::class, 'reservations']);
});

// Calendar .ics feed — signed URL, no auth middleware (user/{user} param validated via signature)
Route::get('/users/{user}/calendar.ics', [CalendarExportController::class, 'ics'])
    ->name('calendar.ics')
    ->middleware('throttle:30,1');

// Generate signed .ics URL (authenticated)
Route::middleware('auth:sanctum')->get('/my/calendar-url', [CalendarExportController::class, 'calendarUrl']);

// Reservations (client)
Route::middleware('auth:sanctum')->group(function (): void {
    // Named limiter viewings.reserve — default 20/min per user (was throttle:5,1;
    // too aggressive for calendar UX + validation retries). Tunable: RL_VIEWINGS_RESERVE.
    Route::post('/ads/{ad}/reservations', [ViewingReservationController::class, 'store'])
        ->middleware('throttle:viewings.reserve');
    Route::get('/my/reservations', [ViewingReservationController::class, 'myReservations']);
    Route::delete('/reservations/{reservation}', [ViewingReservationController::class, 'cancel'])
        ->middleware('throttle:20,1');
});

// Landlord — incoming viewing requests (same guards as other owner API routes)
Route::middleware(['auth:sanctum', 'owner.role', 'panel.role:owner', 'token.role:agent'])->group(function (): void {
    Route::get('/my/viewing-reservations', [ViewingReservationController::class, 'myReservationsAsLandlord']);
    Route::post('/reservations/{reservation}/confirm', [ViewingReservationController::class, 'confirm'])
        ->middleware('throttle:20,1');
    Route::post('/reservations/{reservation}/no-show', [ViewingReservationController::class, 'noShow'])
        ->middleware('throttle:20,1');
    Route::patch('/reservations/{reservation}/notes', [ViewingReservationController::class, 'updateNotes'])
        ->middleware('throttle:30,1');
});
