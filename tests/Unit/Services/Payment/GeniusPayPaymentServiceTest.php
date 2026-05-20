<?php

declare(strict_types=1);

use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payment\GeniusPayPaymentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('payment.gateways.geniuspay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.geniuspay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.geniuspay.base_url', 'https://pay.genius.ci/api/v1/merchant');

    $this->service = app(GeniusPayPaymentService::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validGeniusPayInitiatePayload(array $overrides = []): array
{
    return array_merge([
        'amount' => 150000,
        'currency' => 'XAF',
        'email' => 'test@keyhome.app',
        'phone' => '+237699000000',
        'name' => 'Jean Dupont',
        'tx_ref' => 'KH-2025-ABCDEF',
        'redirect_url' => 'https://test.app/payment/callback',
        'payment_method' => 'mobile_money',
        'meta' => ['payment_type' => 'credit'],
    ], $overrides);
}

it('initiates payment and returns checkout url', function (): void {
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-A1B2C3D4E5',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-A1B2C3D4E5',
                'status' => 'pending',
            ],
        ], 201),
    ]);

    $result = $this->service->initiate(validGeniusPayInitiatePayload());

    expect($result)
        ->toHaveKey('status', 'pending')
        ->toHaveKey('gateway', 'geniuspay')
        ->and($result['link'])->toContain('pay.genius.ci/checkout');
});

it('sends geniuspay auth headers', function (): void {
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-TEST',
                'checkout_url' => 'https://pay.genius.ci/checkout/MTX-TEST',
            ],
        ], 201),
    ]);

    $this->service->initiate(validGeniusPayInitiatePayload());

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-API-Key', 'pk_sandbox_test_fake')
        && $request->hasHeader('X-API-Secret', 'sk_sandbox_test_fake'));
});

it('throws when geniuspay returns error', function (): void {
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => false,
            'error' => ['message' => 'Invalid key'],
        ], 401),
    ]);

    $this->service->initiate(validGeniusPayInitiatePayload());
})->throws(PaymentGatewayException::class);

it('verifies completed transaction by reference', function (): void {
    Http::fake([
        'pay.genius.ci/*' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-A1B2C3D4E5',
                'status' => 'completed',
                'amount' => 150000,
                'currency' => 'XAF',
                'payment_method' => 'mtn_money',
                'completed_at' => '2025-03-07T10:30:00.000000Z',
            ],
        ], 200),
    ]);

    $result = $this->service->verify('MTX-A1B2C3D4E5');

    expect($result)
        ->toHaveKey('status', 'success')
        ->toHaveKey('amount', 150000.0)
        ->toHaveKey('currency', 'XAF');
});

it('validates webhook hmac signature', function (): void {
    $secret = 'whsec_sandbox_test_secret_123';
    $timestamp = (string) time();
    $payload = [
        'event' => 'payment.success',
        'data' => [
            'status' => 'completed',
            'amount' => 5000,
            'currency' => 'XAF',
            'metadata' => ['tx_ref' => 'KH-WH-001'],
        ],
    ];
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $timestamp.'.'.$encoded, $secret);

    $result = $this->service->handleWebhook($payload, [
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Timestamp' => $timestamp,
        'X-Webhook-Event' => 'payment.success',
    ]);

    expect($result['tx_ref'])->toBe('KH-WH-001')
        ->and($result['status'])->toBe('success');
});

it('rejects webhook with invalid signature', function (): void {
    $this->service->handleWebhook(['event' => 'payment.success', 'data' => []], [
        'X-Webhook-Signature' => 'invalid',
        'X-Webhook-Timestamp' => (string) time(),
        'X-Webhook-Event' => 'payment.success',
    ]);
})->throws(InvalidWebhookSignatureException::class);
