<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');

    // routes/web.php
});

Route::get('email/verify/{id}/{hash}', [
    EmailVerificationController::class,
    'verify',
])->name('web.verification.verify');

Route::get('auth/verify-email/{id}/{hash}', [
    \App\Http\Controllers\Auth\VerifyEmailController::class,
    '__invoke',
])->name('verification.verify');

// Route de "Callback" pour redirection sécurisée
Route::get('/verify-email', function (\Illuminate\Http\Request $request) {
    if (!$request->has('verify_url')) {
        abort(400, 'Missing verify_url');
    }

    $verifyUrl = $request->query('verify_url');
    $allowedHosts = [
        'keyhome.neocraft.dev',
        'api.keyhome.neocraft.dev',
        'keyhomeback.neocraft.dev',
        'localhost',
        '127.0.0.1',
    ];

    $parsedHost = parse_url($verifyUrl, PHP_URL_HOST);
    if (!$parsedHost || !in_array($parsedHost, $allowedHosts, true)) {
        abort(403, 'Redirect to untrusted domain is not allowed.');
    }

    return redirect($verifyUrl);
});
