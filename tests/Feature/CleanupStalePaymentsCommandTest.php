<?php

declare(strict_types=1);

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\GeniusPayPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Fausse passerelle : `verify()` renvoie toujours l'état passé au constructeur.
 * Bindée à la place de GeniusPay pour que la commande de réconciliation
 * interroge une passerelle déterministe (pas de HTTP réel).
 */
function fakeGateway(string $status): PaymentGatewayInterface
{
    return new class($status) implements PaymentGatewayInterface
    {
        public function __construct(private string $status) {}

        public function initiate(array $payload): array
        {
            return ['link' => '', 'tx_ref' => '', 'status' => 'pending', 'gateway' => 'geniuspay'];
        }

        public function verify(string $externalReference): array
        {
            return [
                'status' => $this->status,
                'amount' => 0.0,
                'currency' => 'XAF',
                'payment_method' => 'mobile_money',
                'paid_at' => null,
                'raw' => [],
            ];
        }

        public function handleWebhook(array $payload, array $headers): array
        {
            return ['event' => '', 'event_id' => null, 'tx_ref' => '', 'status' => 'pending', 'amount' => 0.0, 'currency' => 'XAF', 'payment_method' => null, 'raw' => []];
        }

        public function getName(): string
        {
            return 'geniuspay';
        }

        public function refund(string $gatewayTransactionId, ?float $amount = null): array
        {
            return ['status' => 'failed', 'raw' => []];
        }
    };
}

it('marks a still-pending stale payment as failed after reconciliation', function () {
    // La passerelle confirme que le paiement est toujours en attente (abandonné).
    $this->app->instance(GeniusPayPaymentService::class, fakeGateway('pending'));

    $payment = Payment::factory()->pending()->geniuspay()->create([
        'created_at' => now()->subHours(48),
    ]);

    $this->artisan('app:cleanup-stale-payments')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::FAILED);
});

it('ignores recent pending payments (within the window)', function () {
    $this->app->instance(GeniusPayPaymentService::class, fakeGateway('pending'));

    $payment = Payment::factory()->pending()->geniuspay()->create([
        'created_at' => now()->subMinutes(30),
    ]);

    $this->artisan('app:cleanup-stale-payments')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::PENDING);
});

it('never re-opens a payment already marked success', function () {
    $this->app->instance(GeniusPayPaymentService::class, fakeGateway('pending'));

    $payment = Payment::factory()->success()->geniuspay()->create([
        'created_at' => now()->subHours(48),
    ]);

    $this->artisan('app:cleanup-stale-payments')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::SUCCESS);
});
