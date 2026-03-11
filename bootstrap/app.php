<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'api/v1/payments/webhook',
        ]);
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'optional.auth' => \App\Http\Middleware\OptionalAuth::class,
        ]);
        // Append is_active check to all sanctum-authenticated API routes
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Livewire tmp-file metadata failures (e.g. iPhone HEIC/large uploads that
        // leave no file on disk) must never produce a 500.  Convert them into a
        // user-friendly validation error so FilePond can display the rejection.
        $exceptions->renderable(function (\League\Flysystem\UnableToRetrieveMetadata $e, \Illuminate\Http\Request $request) {
            if ($request->is('livewire/update') || $request->is('livewire/upload-file')) {
                return response()->json([
                    'message' => 'Le fichier téléversé est invalide ou trop volumineux.',
                    'errors' => ['file' => ['Le fichier téléversé est invalide ou trop volumineux.']],
                ], 422);
            }
        });
    })->create();
