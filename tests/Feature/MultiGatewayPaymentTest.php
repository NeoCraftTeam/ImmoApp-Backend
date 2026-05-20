<?php

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use App\Models\User;
use App\Services\Payment\GeniusPayPaymentService;
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
    $primaryMock = makeGatewayMock(PaymentGateway::GeniusPay->value, [
        'link' => 'https://pay.genius.ci/checkout/MTX-001',
        'tx_ref' => 'KH-TEST001',
        'status' => 'pending',
        'gateway' => 'geniuspay',
    ]);

    $this->app->bind(GeniusPayPaymentService::class, fn () => $primaryMock);

    config(['payment.default' => 'geniuspay', 'payment.fallback' => null]);

    $user = User::factory()->create();
    $result = app(PaymentService::class)->createPayment($user, [
        'amount' => 5000,
        'currency' => 'XAF',
        'type' => 'credit',
        'payment_method' => 'flutterwave',
    ]);

    expect($result['gateway'])->toBe('geniuspay')
        ->and($result['link'])->toBe('https://pay.genius.ci/checkout/MTX-001');
});

it('falls back to secondary gateway when primary fails', function (): void {
    $primaryMock = makeGatewayMock(
        PaymentGateway::GeniusPay->value,
        new PaymentGatewayException('GeniusPay: connexion echouee.')
    );

    $fallbackMock = makeGatewayMock('wave', [
        'link' => 'https://wave.example/pay/xyz',
        'tx_ref' => 'KH-TEST002',
        'status' => 'pending',
        'gateway' => 'wave',
    ]);

    $this->app->bind(GeniusPayPaymentService::class, fn () => $primaryMock);

    config(['payment.default' => 'geniuspay', 'payment.fallback' => null]);

    $service = new PaymentService($primaryMock, $fallbackMock);
    $user = User::factory()->create();
    $result = $service->createPayment($user, [
        'amount' => 5000,
        'currency' => 'XAF',
        'type' => 'credit',
        'payment_method' => 'flutterwave',
    ]);

    expect($result['gateway'])->toBe('wave')
        ->and($result['link'])->toBe('https://wave.example/pay/xyz');
});

it('throws exception when both primary and fallback gateways fail', function (): void {
    $primaryMock = makeGatewayMock(
        PaymentGateway::GeniusPay->value,
        new PaymentGatewayException('GeniusPay: connexion echouee.')
    );

    $fallbackMock = makeGatewayMock(
        'wave',
        new PaymentGatewayException('Wave: service indisponible.')
    );

    $service = new PaymentService($primaryMock, $fallbackMock);
    $user = User::factory()->create();

    expect(fn () => $service->createPayment($user, [
        'amount' => 5000,
        'currency' => 'XAF',
        'type' => 'credit',
        'payment_method' => 'flutterwave',
    ]))->toThrow(PaymentGatewayException::class, 'Wave: service indisponible.');
});

it('propagates primary exception when no fallback is configured', function (): void {
    $primaryMock = makeGatewayMock(
        PaymentGateway::Flutterwave->value,
        new PaymentGatewayException('GeniusPay: cle API invalide.')
    );

    $this->app->bind(GeniusPayPaymentService::class, fn () => $primaryMock);

    config(['payment.default' => 'geniuspay', 'payment.fallback' => null]);

    $user = User::factory()->create();

    expect(fn () => app(PaymentService::class)->createPayment($user, [
        'amount' => 5000,
        'currency' => 'XAF',
        'type' => 'credit',
        'payment_method' => 'flutterwave',
    ]))->toThrow(PaymentGatewayException::class, 'GeniusPay: cle API invalide.');
});
