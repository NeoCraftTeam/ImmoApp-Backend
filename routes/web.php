<?php

use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');

    // routes/web.php
});

Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify');
