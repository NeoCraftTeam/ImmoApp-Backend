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
        $trustedProxies = env('TRUSTED_PROXIES', '127.0.0.1');
        $middleware->trustProxies(at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', (string) $trustedProxies)));
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'api/v1/payments/webhook',
        ]);
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'optional.auth' => \App\Http\Middleware\OptionalAuth::class,
        ]);
        $middleware->prependToGroup('web', \App\Http\Middleware\LivewireLongRunningRequest::class);
        // Append is_active check to all sanctum-authenticated API routes
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Livewire file upload failures must never produce a 500.
        // UnableToRetrieveMetadata: temp file missing (expired, wrong disk, or upload failed)
        $fileExpiredMessage = 'Le fichier a peut-être expiré. Téléchargez-le à nouveau puis soumettez le formulaire rapidement.';
        $fileUploadErrorResponse = fn (string $msg) => response()->json([
            'message' => $msg,
            'errors' => ['file' => [$msg]],
        ], 422);

        $exceptions->renderable(function (\League\Flysystem\UnableToRetrieveMetadata $e, \Illuminate\Http\Request $request) use ($fileUploadErrorResponse, $fileExpiredMessage) {
            if ($request->is('livewire/*')) {
                report($e);

                return $fileUploadErrorResponse($fileExpiredMessage);
            }
        });

        // Catch any exception on the upload endpoint (disk, Flysystem, etc.)
        $exceptions->renderable(function (\Throwable $e, \Illuminate\Http\Request $request) use ($fileUploadErrorResponse) {
            if ($request->is('livewire/upload-file') && !$e instanceof \Illuminate\Validation\ValidationException) {
                report($e);

                return $fileUploadErrorResponse('Le fichier téléversé est invalide ou trop volumineux.');
            }
        });
    })->create();
