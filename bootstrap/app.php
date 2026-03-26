<?php

use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\CacheHeaders;
use App\Http\Middleware\CheckFeatureFlag;
use App\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LivewireLongRunningRequest;
use App\Http\Middleware\OptionalAuth;
use App\Http\Middleware\ResolveSanctumBearerUser;
use App\Http\Middleware\RoleScopedSession;
use App\Http\Middleware\SanitizeInput;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToRetrieveMetadata;
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
        $middleware->trustProxies(at: $trustedProxies === '*' ? '*' : array_map(trim(...), explode(',', (string) $trustedProxies)));
        $middleware->prepend(AddRequestId::class);
        $csrfExcept = [
            'api/*',
            'api/v1/payments/webhook',
        ];
        if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing') {
            $csrfExcept[] = 'panel-api/*';
        }
        $middleware->validateCsrfTokens(except: $csrfExcept);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'cache.headers' => CacheHeaders::class,
            'feature' => CheckFeatureFlag::class,
            'optional.auth' => OptionalAuth::class,
            'resolve.sanctum.bearer' => ResolveSanctumBearerUser::class,
            'role.scoped.session' => RoleScopedSession::class,
        ]);
        $middleware->prependToGroup('web', RoleScopedSession::class);
        $middleware->prependToGroup('web', LivewireLongRunningRequest::class);
        // Enable Sanctum SPA cookie-based authentication for stateful domains
        // Use custom middleware that respects SESSION_SAME_SITE config
        $middleware->statefulApi();
        $middleware->replaceInGroup('api', Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class, EnsureFrontendRequestsAreStateful::class);
        // Append is_active check to all sanctum-authenticated API routes
        $middleware->appendToGroup('api', [
            EnsureUserIsActive::class,
            SanitizeInput::class,
            CacheHeaders::class,
        ]);
        $middleware->throttleApi();
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

        $exceptions->renderable(function (UnableToRetrieveMetadata $e, Request $request) use ($fileUploadErrorResponse, $fileExpiredMessage) {
            if ($request->is('livewire/*')) {
                report($e);

                return $fileUploadErrorResponse($fileExpiredMessage);
            }
        });

        // Catch any exception on the upload endpoint (disk, Flysystem, etc.)
        $exceptions->renderable(function (Throwable $e, Request $request) use ($fileUploadErrorResponse) {
            if ($request->is('livewire/upload-file') && !$e instanceof ValidationException) {
                report($e);

                return $fileUploadErrorResponse('Le fichier téléversé est invalide ou trop volumineux.');
            }
        });
    })->create();
