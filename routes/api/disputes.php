<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DisputeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->controller(DisputeController::class)->group(function (): void {
    // Listing & details — policy filters per-user vs admin.
    Route::get('/disputes', 'index');
    Route::get('/disputes/{dispute}', 'show');

    // Open a dispute. Throttle prevents flood of fake disputes.
    Route::post('/disputes', 'store')
        ->middleware('throttle:10,60'); // 10 per hour per IP

    // Reply with a message inside a dispute.
    Route::post('/disputes/{dispute}/messages', 'storeMessage')
        ->middleware('throttle:60,1'); // 60 per minute

    // Upload evidence (photo / doc / contract).
    Route::post('/disputes/{dispute}/evidences', 'uploadEvidence')
        ->middleware('throttle:30,1');

    // Admin-only state transition. Policy enforces role + permission.
    Route::patch('/disputes/{dispute}/status', 'transition');
});
