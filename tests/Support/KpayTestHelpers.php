<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

/**
 * Stub Kpay GATEWAY init responses for feature tests.
 */
function fakeKpayInitHttp(string $slug = 'test'): void
{
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_'.$slug,
            'reference' => 'KPAY-'.$slug,
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_'.$slug,
        ], 201),
    ]);
}

/**
 * Build signed Kpay webhook headers + raw JSON body.
 *
 * @param  array<string, mixed>  $payload
 * @return array{0: array<string, string>, 1: string}
 */
function signedKpayWebhook(string $secret, array $payload, ?string $event = null): array
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $body, $secret);
    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_KPAY_SIGNATURE' => $signature,
        'HTTP_X_KPAY_EVENT' => $event ?? (string) ($payload['event'] ?? 'payment.completed'),
    ];

    return [$headers, $body];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function kpayCompletedWebhookPayload(array $overrides = []): array
{
    return array_merge([
        'event' => 'payment.completed',
        'paymentId' => 'pay_wh_completed',
        'reference' => 'KPAY-WH-COMPLETED',
        'status' => 'COMPLETED',
        'amount' => 5000,
        'currency' => 'XAF',
    ], $overrides);
}
