<?php

declare(strict_types=1);

use App\Http\Middleware\SanitizeApiErrorResponse;
use App\Support\SafeApiMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

function runSanitizer(mixed $data, int $status, string $path = 'api/v1/whatever'): JsonResponse
{
    $middleware = new SanitizeApiErrorResponse;
    $request = Request::create($path, 'GET');

    $response = $middleware->handle($request, fn () => new JsonResponse($data, $status));

    expect($response)->toBeInstanceOf(JsonResponse::class);

    return $response;
}

it('replaces a sensitive top-level message with the status fallback', function (): void {
    $response = runSanitizer(['message' => 'SQLSTATE[42S02]: Base table not found'], 500);

    expect($response->getData(true)['message'])->toBe(SafeApiMessage::fallbackForStatus(500));
});

it('leaves a safe message untouched', function (): void {
    $safe = 'Ce créneau n\'est pas disponible pour la date demandée.';

    $response = runSanitizer(['message' => $safe, 'code' => 'SLOT_NOT_AVAILABLE'], 410);

    expect($response->getData(true))->toBe(['message' => $safe, 'code' => 'SLOT_NOT_AVAILABLE']);
});

it('drops a sensitive hint but keeps a safe one', function (): void {
    $leak = runSanitizer(['message' => 'Requête invalide.', 'hint' => 'Call to undefined method App\\Models\\Ad::foo()'], 400);
    expect($leak->getData(true))->not->toHaveKey('hint');

    $safe = runSanitizer(['message' => 'Requête invalide.', 'hint' => 'Vérifiez la date.'], 400);
    expect($safe->getData(true)['hint'])->toBe('Vérifiez la date.');
});

it('filters sensitive per-field validation errors and removes emptied fields', function (): void {
    $response = runSanitizer([
        'message' => 'Certains champs sont invalides.',
        'errors' => [
            'email' => ['L\'adresse e-mail est invalide.'],
            'token' => ['Exception in /var/www/app/Http/Controllers/Api/Foo.php'],
        ],
    ], 422);

    $errors = $response->getData(true)['errors'];
    expect($errors)->toHaveKey('email')
        ->and($errors)->not->toHaveKey('token');
});

it('sanitizes the legacy nested error object', function (): void {
    $response = runSanitizer([
        'message' => 'SQLSTATE boom',
        'code' => 'SLOT_NOT_AVAILABLE',
        'error' => ['code' => 'SLOT_NOT_AVAILABLE', 'message' => 'SQLSTATE boom'],
    ], 410);

    $data = $response->getData(true);
    expect($data['message'])->toBe(SafeApiMessage::fallbackForStatus(410))
        ->and($data['error']['message'])->toBe(SafeApiMessage::fallbackForStatus(410));
});

it('strips debug/introspection keys when APP_DEBUG is off', function (): void {
    config(['app.debug' => false]);

    $response = runSanitizer([
        'message' => 'Une erreur interne est survenue.',
        'code' => 'SERVER_ERROR',
        'debug' => ['exception' => 'RuntimeException', 'file' => '/var/www/app.php'],
    ], 500);

    expect($response->getData(true))->not->toHaveKey('debug');
});

it('keeps debug keys when APP_DEBUG is on', function (): void {
    config(['app.debug' => true]);

    $debug = ['exception' => 'RuntimeException'];
    $response = runSanitizer([
        'message' => 'Une erreur interne est survenue.',
        'debug' => $debug,
    ], 500);

    expect($response->getData(true)['debug'])->toBe($debug);
});

it('ignores successful responses even when the message looks sensitive', function (): void {
    $data = ['message' => 'Meilisearch reindex done', 'success' => true];

    $response = runSanitizer($data, 200);

    expect($response->getData(true))->toBe($data);
});

it('is a no-op for non-array json payloads', function (): void {
    $response = runSanitizer('Erreur SQLSTATE brute', 500);

    expect($response->getData(true))->toBe('Erreur SQLSTATE brute');
});
