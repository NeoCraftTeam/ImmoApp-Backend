<?php

declare(strict_types=1);

use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payment\KpayPaymentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('payment.gateways.kpay.api_key', 'kpay_test_fake');
    config()->set('payment.gateways.kpay.api_secret', 'sk_test_fake');
    config()->set('payment.gateways.kpay.webhook_secret', 'whsec_kpay_test_secret_123');
    config()->set('payment.gateways.kpay.base_url', 'https://admin.kpay.site');

    $this->service = app(KpayPaymentService::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validKpayInitiatePayload(array $overrides = []): array
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

it('initiates payment and returns gateway url', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_abc123',
            'reference' => 'KPAY-20260514-ABC123',
            'externalId' => 'KH-2025-ABCDEF',
            'status' => 'PENDING',
            'mode' => 'GATEWAY',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_test',
        ], 201),
    ]);

    $result = $this->service->initiate(validKpayInitiatePayload());

    expect($result)
        ->toHaveKey('status', 'pending')
        ->toHaveKey('gateway', 'kpay')
        ->and($result['link'])->toContain('admin.kpay.site/gateway');
});

it('sends externalId as tx_ref to kpay init', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_ext',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_ext',
        ], 201),
    ]);

    $this->service->initiate(validKpayInitiatePayload(['tx_ref' => 'KH-EXT-REF-001']));

    Http::assertSent(fn (Request $request) => $request->data()['externalId'] === 'KH-EXT-REF-001');
});

it('sends currency to kpay init because GATEWAY mode requires it', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_cur',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_cur',
        ], 201),
    ]);

    $this->service->initiate(validKpayInitiatePayload(['currency' => 'cdf']));

    // Regression: prod init failed with "le champ currency est obligatoire en
    // mode GATEWAY". The service must forward the payload currency, uppercased.
    Http::assertSent(fn (Request $request) => ($request->data()['currency'] ?? null) === 'CDF');
});

it('defaults currency to the configured ledger currency when the payload passes it blank', function (): void {
    config()->set('payment.default_currency', 'XAF');

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_def',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_def',
        ], 201),
    ]);

    $this->service->initiate(validKpayInitiatePayload(['currency' => '']));

    Http::assertSent(fn (Request $request) => ($request->data()['currency'] ?? null) === 'XAF');
});

it('sends kpay auth headers', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_test',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_test',
        ], 201),
    ]);

    $this->service->initiate(validKpayInitiatePayload());

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-API-Key', 'kpay_test_fake')
        && $request->hasHeader('X-Secret-Key', 'sk_test_fake'));
});

it('throws when kpay returns error', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'message' => 'Invalid key',
        ], 401),
    ]);

    $this->service->initiate(validKpayInitiatePayload());
})->throws(PaymentGatewayException::class);

it('verifies completed transaction by kpay id', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_abc123',
            'reference' => 'KPAY-20260514-ABC123',
            'status' => 'COMPLETED',
            'amount' => 150000,
            'currency' => 'XAF',
            'provider' => 'MTN_MOMO_CMR',
            'completedAt' => '2025-03-07T10:30:00.000Z',
        ], 200),
    ]);

    $result = $this->service->verify('pay_abc123');

    expect($result)
        ->toHaveKey('status', 'success')
        ->toHaveKey('amount', 150000.0)
        ->toHaveKey('currency', 'XAF');
});

it('maps XOF currency from kpay verify response', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_xof',
            'status' => 'COMPLETED',
            'amount' => 5000,
            'currency' => 'XOF',
        ], 200),
    ]);

    $result = $this->service->verify('pay_xof');

    expect($result)
        ->toHaveKey('status', 'success')
        ->toHaveKey('currency', 'XOF');
});

it('returns pending when kpay verify HTTP call fails', function (): void {
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'message' => 'Not found',
        ], 404),
    ]);

    $result = $this->service->verify('pay_missing');

    expect($result)->toHaveKey('status', 'pending');
});

it('validates webhook hmac signature on raw body', function (): void {
    $secret = 'whsec_kpay_test_secret_123';
    $payload = [
        'event' => 'payment.completed',
        'paymentId' => 'pay_wh_001',
        'reference' => 'KPAY-WH-001',
        'status' => 'COMPLETED',
        'amount' => 5000,
        'externalId' => 'KH-WH-001',
    ];
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', (string) $encoded, $secret);

    $result = $this->service->handleWebhook($payload, [
        'X-KPAY-Signature' => $signature,
        'X-KPAY-Event' => 'payment.completed',
    ], (string) $encoded);

    expect($result['tx_ref'])->toBe('KH-WH-001')
        ->and($result['status'])->toBe('success');
});

it('rejects webhook with invalid signature', function (): void {
    $this->service->handleWebhook(['event' => 'payment.completed', 'externalId' => 'KH-X'], [
        'X-KPAY-Signature' => 'invalid',
        'X-KPAY-Event' => 'payment.completed',
    ], '{"event":"payment.completed"}');
})->throws(InvalidWebhookSignatureException::class);
