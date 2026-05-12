<?php

declare(strict_types=1);

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Services\Payment\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Stripe webhook integration tests
|--------------------------------------------------------------------------
|
| These tests exercise the full HTTP webhook path
| (`POST /api/v1/webhooks/stripe`) but stub the Stripe gateway so we never
| call the real SDK and never need to forge a `Stripe-Signature` HMAC.
|
| The stub is bound to `StripePaymentService::class` and the
| `PaymentService` singleton is forgotten so its gateway registry is
| rebuilt with our fake on the next resolve.
*/

/**
 * Build a fake gateway that returns the given normalised webhook payload
 * from `handleWebhook()` and identifies itself as the Stripe gateway.
 *
 * @param  array{event:string,tx_ref:string,status:string,amount:float,currency:string,payment_method:?string,raw:array<string,mixed>}  $payload
 */
function fakeStripeGateway(array $payload): PaymentGatewayInterface
{
    return new readonly class($payload) implements PaymentGatewayInterface
    {
        public function __construct(private array $payload) {}

        public function getName(): string
        {
            return 'stripe';
        }

        public function initiate(array $payload): array
        {
            return ['link' => '', 'tx_ref' => '', 'status' => 'pending', 'gateway' => 'stripe'];
        }

        public function verify(string $externalReference): array
        {
            return ['status' => 'pending', 'amount' => 0.0, 'currency' => 'XAF', 'payment_method' => null, 'paid_at' => null, 'raw' => []];
        }

        public function handleWebhook(array $payload, array $headers): array
        {
            return $this->payload;
        }

        public function refund(string $gatewayTransactionId, ?float $amount = null): array
        {
            return ['refund_id' => '', 'status' => 'pending', 'amount_refunded' => 0.0, 'raw' => []];
        }
    };
}

function bindStripeGatewayStub(PaymentGatewayInterface $stub): void
{
    app()->instance(StripePaymentService::class, $stub);
    // `PaymentService` is registered as a singleton in `AppServiceProvider`
    // and snapshots the gateway registry at construction. Forgetting the
    // instance forces it to rebuild with our stub on the next resolve.
    app()->forgetInstance(PaymentService::class);
}

it('promotes a pending Stripe payment to SUCCESS on payment_intent.succeeded', function (): void {
    Event::fake([PaymentSucceeded::class]);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => PaymentStatus::PENDING,
        'gateway' => 'stripe',
        'amount' => 1000,
        'transaction_id' => 'KH-STRIPE-WH-001',
    ]);

    bindStripeGatewayStub(fakeStripeGateway([
        'event' => 'payment_intent.succeeded',
        'tx_ref' => 'KH-STRIPE-WH-001',
        'status' => 'success',
        'amount' => 1000.0,
        'currency' => 'XAF',
        'payment_method' => 'card',
        'raw' => ['id' => 'pi_test_001', 'status' => 'succeeded'],
    ]));

    $response = $this->postJson('/api/v1/webhooks/stripe', ['id' => 'evt_test_001'], [
        'Stripe-Signature' => 'test-signature-bypassed-by-stub',
    ]);

    $response->assertOk()->assertJsonPath('status', 'ok');

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::SUCCESS);

    Event::assertDispatched(PaymentSucceeded::class, fn ($e) => $e->payment->id === $payment->id);
});

it('marks a pending Stripe payment as FAILED on payment_intent.payment_failed', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatus::PENDING,
        'gateway' => 'stripe',
        'amount' => 2500,
        'transaction_id' => 'KH-STRIPE-WH-FAIL',
    ]);

    bindStripeGatewayStub(fakeStripeGateway([
        'event' => 'payment_intent.payment_failed',
        'tx_ref' => 'KH-STRIPE-WH-FAIL',
        'status' => 'failed',
        'amount' => 2500.0,
        'currency' => 'XAF',
        'payment_method' => null,
        'raw' => ['id' => 'pi_test_fail', 'status' => 'requires_payment_method'],
    ]));

    $this->postJson('/api/v1/webhooks/stripe', ['id' => 'evt_test_fail'], [
        'Stripe-Signature' => 'test-signature',
    ])->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::FAILED);
});

it('reopens a CANCELLED Stripe payment when the gateway later confirms a real charge (orphan-debit guard)', function (): void {
    Event::fake([PaymentSucceeded::class]);
    Log::spy();

    // Reproduces the multi-tab race scenario: user clicks « Annuler » in
    // the modal (local row → CANCELLED) then completes 3DS in another tab,
    // so Stripe charges the card and emits `payment_intent.succeeded`.
    $payment = Payment::factory()->create([
        'status' => PaymentStatus::CANCELLED,
        'gateway' => 'stripe',
        'amount' => 5000,
        'transaction_id' => 'KH-STRIPE-ORPHAN-001',
    ]);

    bindStripeGatewayStub(fakeStripeGateway([
        'event' => 'payment_intent.succeeded',
        'tx_ref' => 'KH-STRIPE-ORPHAN-001',
        'status' => 'success',
        'amount' => 5000.0,
        'currency' => 'XAF',
        'payment_method' => 'card',
        'raw' => ['id' => 'pi_orphan_001', 'status' => 'succeeded'],
    ]));

    $this->postJson('/api/v1/webhooks/stripe', ['id' => 'evt_orphan_001'], [
        'Stripe-Signature' => 'test-signature',
    ])->assertOk();

    // The customer's card was charged → we MUST fulfil rather than ignore.
    expect($payment->refresh()->status)->toBe(PaymentStatus::SUCCESS);
    Event::assertDispatched(PaymentSucceeded::class);

    // Support visibility: a critical log MUST be emitted so the incident
    // is surfaced even though the row is fulfilled normally.
    Log::shouldHaveReceived('critical')
        ->withArgs(fn ($message, $context = []) => str_contains((string) $message, 'orphan debit')
            && ($context['previous_status'] ?? null) === 'cancelled'
        )
        ->once();
});

it('still ignores duplicate success on an already-SUCCESS Stripe payment', function (): void {
    Event::fake([PaymentSucceeded::class]);

    $payment = Payment::factory()->create([
        'status' => PaymentStatus::SUCCESS,
        'gateway' => 'stripe',
        'amount' => 1000,
        'transaction_id' => 'KH-STRIPE-DUP-001',
    ]);

    bindStripeGatewayStub(fakeStripeGateway([
        'event' => 'payment_intent.succeeded',
        'tx_ref' => 'KH-STRIPE-DUP-001',
        'status' => 'success',
        'amount' => 1000.0,
        'currency' => 'XAF',
        'payment_method' => 'card',
        'raw' => ['id' => 'pi_dup_001', 'status' => 'succeeded'],
    ]));

    $this->postJson('/api/v1/webhooks/stripe', ['id' => 'evt_dup_001'], [
        'Stripe-Signature' => 'test-signature',
    ])->assertOk();

    // Status untouched, no duplicate event dispatched.
    expect($payment->refresh()->status)->toBe(PaymentStatus::SUCCESS);
    Event::assertNotDispatched(PaymentSucceeded::class);
});
