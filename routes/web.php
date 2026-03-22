<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\TourController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\EmailPreferenceController;
use App\Http\Controllers\MediaProxyController;
use App\Http\Controllers\PanelSsoController;
use App\Http\Controllers\PwaManifestController;
use App\Http\Controllers\TourImageProxyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/manifest.json', PwaManifestController::class)->name('pwa.manifest');

Route::get('/', function () {
    $host = request()->getHost();

    if (config('filament.panels.admin_domain') && $host === config('filament.panels.admin_domain')) {
        return redirect('/login');
    }

    if (config('filament.panels.agency_domain') && $host === config('filament.panels.agency_domain')) {
        return redirect('/login');
    }

    if (config('filament.panels.owner_domain') && $host === config('filament.panels.owner_domain')) {
        return redirect('/login');
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
    VerifyEmailController::class,
    '__invoke',
])->name('verification.verify');

// Route de "Callback" pour redirection sécurisée
Route::get('/verify-email', function (Request $request) {
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

// ── Fallback login route — required by the web `auth` middleware redirect ──
Route::get('/login', fn () => redirect('/owner/login'))->name('login');

// ── Tour image proxy — streams R2 images with CORS headers for Photo Sphere Viewer (XHR-based) ──
// The {path} parameter uses `.+` to match nested tile paths like scenes/{id}/tiles/1/f0_0.webp
Route::get('/tour-image/{adId}/{path}', [TourImageProxyController::class, 'show'])
    ->where('adId', 'temp|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}')
    ->where('path', '.+')
    ->middleware(['throttle:120,1', 'resolve.sanctum.bearer'])
    ->name('tour.image.proxy');

// ── Media proxy — serves Spatie Media Library files from R2 for Filament FilePond previews ──
Route::get('/media-proxy/{uuid}', [MediaProxyController::class, 'show'])
    ->where('uuid', '[0-9a-f\-]+')
    ->name('media.proxy');

// ── Email Preferences (public — accessed via token link in emails, no auth) ──
Route::prefix('email')->group(function (): void {
    Route::get('/unsubscribe/{token}', [EmailPreferenceController::class, 'unsubscribe'])
        ->middleware('throttle:30,1')
        ->name('email.unsubscribe');
    Route::get('/preferences/{token}', [EmailPreferenceController::class, 'manage'])
        ->middleware('throttle:30,1')
        ->name('email.preferences');
    Route::post('/preferences/{token}', [EmailPreferenceController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('email.preferences.update');
});

// ── Panel Tour Routes (web session auth — called from Filament Blade components) ──
Route::middleware(['auth'])->prefix('panel-api/v1')->group(function (): void {
    Route::post('/ads/{ad}/tour/scenes', [TourController::class, 'uploadScenes'])
        ->middleware('throttle:10,1');
    Route::match(['patch', 'post'], '/ads/{ad}/tour/scenes/{sceneId}/hotspots', [TourController::class, 'updateHotspots']);
    Route::delete('/ads/{ad}/tour', [TourController::class, 'destroy']);
});
