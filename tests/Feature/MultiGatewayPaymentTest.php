<?php

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use App\Models\User;
use App\Services\Payment\FedaPayPaymentService;
use App\Services\Payment\FlutterwavePaymentService;
use App\Services\Payment\PaymentService;

/**
 * Build a mock gateway implementing PaymentGatewayInterface.
 *
 * @param  array<string, mixed>|PaymentGatewayException  $initiateResult
 */
function makeGatewayMock(string $name, array|PaymentGatewayException $initiateResult): PaymentGatewayInterface
{
    $mock = Mockery::mock(PaymentGatewayInterface::class);
    $mock->shouldReceive('getName')->andReturn($name);

    if ($initiateResult instanceof PaymentGatewayException) {
        $mock->shouldReceive('initiate')->once()->andThrow($initiateResult);
    } else {
        $mock->shouldReceive('initiate')->once()->andReturn($initiateResult);
    }

    return $mock;
}

it('uses primary gateway when it succeeds', function (): void {
    $primaryMock = makeGatewayMock(PaymentGateway::Flutterwave->value, [
        'link' => 'https://flutterwave.example/pay/abc',
        'tx_ref' => 'KH-TEST001',
        'status' => 'pending',
        'gateway' => 'flutterwave',
    ]);

    $this->app->bind(FlutterwavePaymentService::class, fn () => $primaryMock);

    config(['payment.default' => 'flutterwave', 'payment.fallback' => null]);

    $user = User::factory()->create();
    $result = (new PaymentService)->createPayment($user, [
        'amount' => 5000,
        'currency' => 'XAF',
        'type' => 'credit',
        'payment_method' => 'flutterwave',
    ]);

    expect($result['gateway'])->toBe('flutterwave')
        ->and($result['link'])->toBe('https://flutterwave.example/pay/abc');
});

it('falls back to secondary gateway when primary fails', function (): void {
    $primaryMock = makeGatewayMock(
        PaymentGateway::Flutterwave->value,
        new PaymentGatewayException('Flutterwave: connexion echouee.')
    );

    $fallbackMock = makeGatewayMock(PaymentGateway::FedaPay->value, [
        'link' => 'https://fedapay.example/pay/xyz',
        'tx_ref' => 'KH-TEST002',
        'status' => 'pending',
        'gateway' => 'fedapay',
    ]);

    $this->app->bind(FlutterwavePaymentService::class, fn () => $primaryMock);
    $this->app->bind(FedaPayPaymentService::class, fn () => $fallbackMock);

    config(['payment.default' => 'flutterwave', 'payment.fallback' => 'fedapay']);

    $user = User::factory()->create();
    $result = (new PaymentService)->createPayment($user, [
        'amount' => 5000,
        'currency' => 'XAF',
        'type' => 'credit',
        'payment_method' => 'flutterwave',
    ]);

    expect($result['gateway'])->toBe('fedapay')
        ->and($result['link'])->toBe('https://fedapay.example/pay/xyz');
});

it('throws exception when both primary and fallback gateways fail', function (): void {
    $primaryMock = makeGatewayMock(
        PaymentGateway::Flutterwave->value,
        new PaymentGatewayException('Flutterwave: connexion echouee.')
    );

    $fallbackMock = makeGatewayMock(
        PaymentGateway::FedaPay->value,
        new PaymentGatewayException('FedaPay: service indisponible.')
    );

    $this->app->bind(FlutterwavePaymentService::class, fn () => $primaryMock);
    $this->app->bind(FedaPayPaymentService::class, fn () => $fallbackMock);

    config(['payment.default' => 'flutterwave', 'payment.fallback' => 'fedapay']);

    $user = User::factory()->create();

    expect(fn () => (new PaymentService)->createPayment($user, [
        'amount' => 5000,
        'currency' => 'XAF',
        'type' => 'credit',
        'payment_method' => 'flutterwave',
    ]))->toThrow(PaymentGatewayException::class, 'FedaPay: service indisponible.');
});

it('propagates primary exception when no fallback is configured', function (): void {
    $primaryMock = makeGatewayMock(
        PaymentGateway::Flutterwave->value,
        new PaymentGatewayException('Flutterwave: cle API invalide.')
    );

    $this->app->bind(FlutterwavePaymentService::class, fn () => $primaryMock);

    config(['payment.default' => 'flutterwave', 'payment.fallback' => null]);

    $user = User::factory()->create();

    expect(fn () => (new PaymentService)->createPayment($user, [
        'amount' => 5000,
        'currency' => 'XAF',
        'type' => 'credit',
        'payment_method' => 'flutterwave',
    ]))->toThrow(PaymentGatewayException::class, 'Flutterwave: cle API invalide.');
});

it('returns 401 for invalid fedapay webhook signature', function (): void {
    config(['payment.gateways.fedapay.webhook_secret' => 'test-secret']);

    $payload = [
        'name' => 'transaction.approved',
        'entity' => [
            'id' => 123,
            'status' => 'approved',
            'amount' => 5000,
            'currency' => ['iso' => 'XAF'],
            'custom_metadata' => ['tx_ref' => 'KH-TEST001'],
        ],
    ];

    $this->postJson('/api/v1/webhooks/fedapay', $payload, [
        'x-fedapay-signature' => 'invalidsignature',
    ])->assertUnauthorized();
});

it('returns 200 for fedapay webhook with valid signature', function (): void {
    $webhookSecret = 'test-fedapay-secret';
    config(['payment.gateways.fedapay.webhook_secret' => $webhookSecret]);

    $payload = [
        'name' => 'transaction.pending',
        'entity' => [
            'id' => 456,
            'status' => 'pending',
            'amount' => 3000,
            'currency' => ['iso' => 'XAF'],
            'custom_metadata' => ['tx_ref' => 'KH-NONEXISTENT'],
        ],
    ];

    $signature = hash_hmac('sha256', (string) json_encode($payload), $webhookSecret);

    $this->postJson('/api/v1/webhooks/fedapay', $payload, [
        'x-fedapay-signature' => $signature,
    ])->assertOk();
});
