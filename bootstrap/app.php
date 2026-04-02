<?php

use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\CacheHeaders;
use App\Http\Middleware\CheckFeatureFlag;
use App\Http\Middleware\EnsureCorrectRoleForPanel;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Http\Middleware\EnsureOwnerRole;
use App\Http\Middleware\EnsureTokenMatchesRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LivewireLongRunningRequest;
use App\Http\Middleware\OptionalAuth;
use App\Http\Middleware\RequireApiMfa;
use App\Http\Middleware\ResolveSanctumBearerUser;
use App\Http\Middleware\RoleScopedSession;
use App\Http\Middleware\SanitizeInput;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToRetrieveMetadata;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        $middleware->append(SecurityHeaders::class);
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
            'email.verified' => EnsureEmailIsVerified::class,
            'feature' => CheckFeatureFlag::class,
            'mfa.admin' => RequireApiMfa::class,
            'optional.auth' => OptionalAuth::class,
            'owner.role' => EnsureOwnerRole::class,
            'panel.role' => EnsureCorrectRoleForPanel::class,
            'resolve.sanctum.bearer' => ResolveSanctumBearerUser::class,
            'role.scoped.session' => RoleScopedSession::class,
            'token.role' => EnsureTokenMatchesRole::class,
        ]);
        $middleware->prependToGroup('web', RoleScopedSession::class);
        $middleware->prependToGroup('web', LivewireLongRunningRequest::class);
        // Enable Sanctum SPA cookie-based authentication for stateful domains
        // Use custom middleware that respects SESSION_SAME_SITE config
        $middleware->statefulApi();
        $middleware->replaceInGroup('api', Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class, EnsureFrontendRequestsAreStateful::class);
        // Append is_active check, email verification, and input sanitization to all API routes
        $middleware->appendToGroup('api', [
            EnsureUserIsActive::class,
            EnsureEmailIsVerified::class,
            SanitizeInput::class,
            CacheHeaders::class,
        ]);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Ressource introuvable.',
                    'code' => 'NOT_FOUND',
                ], 404);
            }
        });

        $exceptions->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Action non autorisée.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        });

        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Non authentifié.',
                    'code' => 'UNAUTHENTICATED',
                ], 401);
            }
        });

        $exceptions->renderable(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Trop de requêtes. Veuillez réessayer dans quelques instants.',
                    'code' => 'RATE_LIMITED',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
                ], 429);
            }
        });

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

        // Generic API 500 handler — registered LAST so specific handlers above take priority.
        // Returns consistent JSON for any unhandled exception on API routes.
        // ValidationException, HttpException (abort()), and known HTTP exceptions are excluded
        // so they flow through Laravel's native renderer with their correct status codes.
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if (
                $request->is('api/*')
                && !$e instanceof ValidationException
                && !$e instanceof HttpException
                && !$e instanceof AuthenticationException
                && !$e instanceof AuthorizationException
                && !$e instanceof ModelNotFoundException
                && !$e instanceof ThrottleRequestsException
            ) {
                return response()->json([
                    'message' => 'Une erreur interne est survenue.',
                    'code' => 'SERVER_ERROR',
                ], 500);
            }
        });
    })->create();
