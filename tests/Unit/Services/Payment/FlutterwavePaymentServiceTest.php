<?php

use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payment\FlutterwavePaymentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');
    config()->set('payment.gateways.flutterwave.webhook_secret', 'test_webhook_secret_123');
    config()->set('payment.gateways.flutterwave.base_url', 'https://api.flutterwave.com/v3');

    $this->service = app(FlutterwavePaymentService::class);
});

function validInitiatePayload(array $overrides = []): array
{
    return array_merge([
        'amount' => 150000,
        'currency' => 'XAF',
        'email' => 'test@keyhome.app',
        'phone' => '+237699000000',
        'name' => 'Jean Dupont',
        'tx_ref' => 'TXN-2025-ABCDEF',
        'redirect_url' => 'https://test.app/payment/callback',
        'payment_type' => 'rent',
    ], $overrides);
}

// ─── INITIATION ──────────────────────────────────────────────────────────

it('should initiate payment successfully when payload is valid', function (): void {
    Http::fake([
        'api.flutterwave.com/v3/payments' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/test123'],
        ], 200),
    ]);

    $result = $this->service->initiate(validInitiatePayload());

    expect($result)
        ->toHaveKey('status', 'pending')
        ->toHaveKey('gateway', 'flutterwave')
        ->and($result['link'])->toContain('checkout.flutterwave.com');
});

it('should send correct authorization header to flutterwave', function (): void {
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://test'],
        ], 200),
    ]);

    $this->service->initiate(validInitiatePayload());

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer FLWSECK_TEST-fake'));
});

it('should throw PaymentGatewayException when flutterwave returns error', function (): void {
    Http::fake([
        'api.flutterwave.com/v3/payments' => Http::response([
            'status' => 'error',
            'message' => 'Invalid key',
        ], 400),
    ]);

    $this->service->initiate(validInitiatePayload());
})->throws(PaymentGatewayException::class);

it('should throw PaymentGatewayException when flutterwave is unreachable', function (): void {
    Http::fake([
        'api.flutterwave.com/*' => Http::response(null, 503),
    ]);

    $this->service->initiate(validInitiatePayload());
})->throws(PaymentGatewayException::class, 'Gateway indisponible');

// ─── VÉRIFICATION ────────────────────────────────────────────────────────

it('should verify transaction successfully when reference is valid', function (): void {
    Http::fake([
        'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'successful',
                'amount' => 150000,
                'currency' => 'XAF',
                'payment_type' => 'mobilemoneycameroon',
                'created_at' => '2025-03-07T10:30:00.000Z',
            ],
        ], 200),
    ]);

    $result = $this->service->verify('TXN-2025-ABCDEF');

    expect($result)
        ->toHaveKey('status', 'success')
        ->toHaveKey('amount', 150000.0)
        ->toHaveKey('currency', 'XAF');
});

it('should return failed status when transaction was not paid', function (): void {
    Http::fake([
        'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'failed', 'amount' => 150000, 'currency' => 'XAF'],
        ], 200),
    ]);

    $result = $this->service->verify('TXN-FAILED-001');

    expect($result)->toHaveKey('status', 'failed');
});

// ─── WEBHOOK ─────────────────────────────────────────────────────────────

it('should validate webhook successfully when signature is correct', function (): void {
    $secret = config('payment.gateways.flutterwave.webhook_secret');
    $payload = ['event' => 'charge.completed', 'data' => ['tx_ref' => 'TXN-001', 'status' => 'successful']];
    $headers = ['verif-hash' => $secret];

    $result = $this->service->handleWebhook($payload, $headers);

    expect($result)
        ->toHaveKey('tx_ref', 'TXN-001')
        ->and($result['status'])->toBe('success');
});

it('should reject webhook when signature is invalid', function (): void {
    $this->service->handleWebhook(
        ['event' => 'charge.completed', 'data' => ['tx_ref' => 'TXN-001']],
        ['verif-hash' => 'invalid_signature_hacker']
    );
})->throws(InvalidWebhookSignatureException::class);

it('should reject webhook when signature header is missing', function (): void {
    $this->service->handleWebhook(
        ['event' => 'charge.completed', 'data' => ['tx_ref' => 'TXN-001']],
        []
    );
})->throws(InvalidWebhookSignatureException::class);

it('should ignore non charge completed webhook events', function (): void {
    $secret = config('payment.gateways.flutterwave.webhook_secret');
    $payload = ['event' => 'transfer.completed', 'data' => ['id' => 999]];
    $headers = ['verif-hash' => $secret];

    $result = $this->service->handleWebhook($payload, $headers);

    expect($result)
        ->toHaveKey('status', 'ignored')
        ->and($result['tx_ref'] ?? null)->toBeEmpty();
});
