<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;

Route::get('/', function () {
    return view('welcome');

    // routes/web.php
});

Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify');
