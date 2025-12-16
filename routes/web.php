<?php

use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');

    // routes/web.php
});

Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('web.verification.verify');

// Route de "Callback" pour simuler le frontend
Route::get('/verify-email', function (\Illuminate\Http\Request $request) {
    if (!$request->has('verify_url')) {
        abort(400, 'Missing verify_url');
    }
    return redirect($request->query('verify_url'));
});
