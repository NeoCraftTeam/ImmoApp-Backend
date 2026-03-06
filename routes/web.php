<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\PanelSsoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $host = request()->getHost();

    if (config('filament.panels.admin_domain') && $host === config('filament.panels.admin_domain')) {
        return redirect('/admin');
    }

    if (config('filament.panels.agency_domain') && $host === config('filament.panels.agency_domain')) {
        return redirect('/agency');
    }

    if (config('filament.panels.owner_domain') && $host === config('filament.panels.owner_domain')) {
        return redirect('/owner');
    }

    return view('welcome');
});

// Clerk → Filament panel SSO (URL signée, valide 60 secondes)
Route::get('/auth/panel-sso', PanelSsoController::class)
    ->middleware('throttle:5,1')
    ->name('panel.sso');

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

    if (!is_string($verifyUrl) || !filter_var($verifyUrl, FILTER_VALIDATE_URL)) {
        abort(403, 'Invalid URL format.');
    }

    $allowedHosts = [
        'keyhome.neocraft.dev',
        'api.keyhome.neocraft.dev',
        'keyhomeback.neocraft.dev',
        'localhost',
        '127.0.0.1',
    ];

    $parsedHost = parse_url($verifyUrl, PHP_URL_HOST);
    $parsedScheme = parse_url($verifyUrl, PHP_URL_SCHEME);

    if (!$parsedHost
        || !in_array($parsedHost, $allowedHosts, true)
        || !in_array($parsedScheme, ['http', 'https'], true)) {
        abort(403, 'Redirect to untrusted domain is not allowed.');
    }

    return redirect($verifyUrl);
});
