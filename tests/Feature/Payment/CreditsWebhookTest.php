<?php

declare(strict_types=1);

use App\Actions\HandlePostPaymentActions;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Jobs\ProcessFlutterwaveWebhookJob;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('payment.default', 'flutterwave');
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');
    config()->set('payment.gateways.flutterwave.webhook_secret', 'test_webhook_secret_456');
    Mail::fake();
});

// ─── WEBHOOK CREDITS UN PAIEMENT ─────────────────────────────────────────

it('credits the user balance when a Flutterwave webhook confirms a credit purchase', function (): void {
    Event::fake();
    Bus::fake();
    $secret = config('payment.gateways.flutterwave.webhook_secret');

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create([
        'price' => 5000,
        'points_awarded' => 50,
        'is_active' => true,
    ]);
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 5000,
        'type' => PaymentType::CREDIT->value,
        'plan_id' => $package->id,
    ]);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => $payment->transaction_id,
            'status' => 'successful',
            'amount' => 5000,
            'currency' => 'XAF',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => $secret,
    ], $payload)->assertSuccessful();

    // The webhook controller marks Payment as SUCCESS synchronously, then
    // dispatches a job for post-payment fulfilment. With Bus::fake the job
    // is captured but not executed, so we run the action manually to assert
    // end-to-end behaviour. (The dispatched job is asserted separately.)
    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::SUCCESS);

    Bus::assertDispatched(ProcessFlutterwaveWebhookJob::class);

    app(HandlePostPaymentActions::class)->execute($payment);

    expect($user->fresh()->point_balance)->toBe(50);
    expect(
        PointTransaction::query()
            ->where('user_id', $user->id)
            ->where('payment_id', $payment->id)
            ->where('type', PointTransactionType::PURCHASE)
            ->count()
    )->toBe(1);
});

// ─── IDEMPOTENCE — REPLAY DU MÊME WEBHOOK ───────────────────────────────

it('does not double-credit when the post-payment action runs twice for the same credit purchase', function (): void {
    Mail::fake();
    Event::fake();

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create([
        'price' => 8000,
        'points_awarded' => 80,
        'is_active' => true,
    ]);
    $payment = Payment::factory()->success()->create([
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 8000,
        'type' => PaymentType::CREDIT->value,
        'plan_id' => $package->id,
    ]);

    $action = app(HandlePostPaymentActions::class);
    $action->execute($payment);
    $action->execute($payment); // simulate webhook retry / job re-dispatch

    expect($user->fresh()->point_balance)->toBe(80);
    expect(
        PointTransaction::query()
            ->where('payment_id', $payment->id)
            ->count()
    )->toBe(1);
});

// ─── IDEMPOTENCE — VERIFY-PURCHASE APRÈS WEBHOOK ────────────────────────

it('verify-purchase does not double-credit when called after the webhook already credited', function (): void {
    Event::fake();
    $secret = config('payment.gateways.flutterwave.webhook_secret');

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create([
        'price' => 3000,
        'points_awarded' => 25,
        'is_active' => true,
    ]);
    $payment = Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 3000,
        'type' => PaymentType::CREDIT->value,
        'plan_id' => $package->id,
    ]);

    // 1) Webhook lands first → Payment SUCCESS + job dispatched
    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => [
            'tx_ref' => $payment->transaction_id,
            'status' => 'successful',
            'amount' => 3000,
            'currency' => 'XAF',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => $secret,
    ], $payload)->assertSuccessful();

    // Simulate the queue worker draining the webhook job (which calls execute()).
    app(HandlePostPaymentActions::class)->execute($payment->fresh());

    expect($user->fresh()->point_balance)->toBe(25);

    // 2) User comes back from Flutterwave → /credits/verify-purchase fires
    //    with the same tx_ref. The endpoint must short-circuit on the
    //    already-SUCCESS status without re-crediting.
    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase', [
            'tx_ref' => $payment->transaction_id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('point_balance', 25);

    expect($user->fresh()->point_balance)->toBe(25);
    expect(
        PointTransaction::query()->where('payment_id', $payment->id)->count()
    )->toBe(1);
});

// ─── VERIFY-PURCHASE TARGETING — PARAM tx_ref ───────────────────────────

it('verify-purchase with tx_ref targets the exact payment, not the latest', function (): void {
    // Stub the Flutterwave verify call so the "latest pending payment" branch
    // doesn't reach the real API. Returns "pending" so the assertion below
    // exercises the targeting logic without short-circuiting on success.
    Http::fake([
        'api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => [
                'tx_ref' => 'KH-IGNORED',
                'status' => 'pending',
                'amount' => 2000,
                'currency' => 'XAF',
            ],
        ], 200),
    ]);

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create([
        'price' => 2000,
        'points_awarded' => 20,
        'is_active' => true,
    ]);

    // Older successful purchase (already credited).
    $oldPayment = Payment::factory()->success()->create([
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 2000,
        'type' => PaymentType::CREDIT->value,
        'plan_id' => $package->id,
        'created_at' => now()->subDays(2),
    ]);
    PointTransaction::create([
        'user_id' => $user->id,
        'type' => PointTransactionType::PURCHASE,
        'points' => 20,
        'description' => 'Old purchase',
        'payment_id' => $oldPayment->id,
    ]);
    $user->increment('point_balance', 20);

    // New pending purchase.
    Payment::factory()->pending()->create([
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 2000,
        'type' => PaymentType::CREDIT->value,
        'plan_id' => $package->id,
        'created_at' => now(),
    ]);

    // Without tx_ref → "latest" → returns the new pending one.
    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase', [])
        ->assertSuccessful()
        ->assertJsonPath('status', 'pending');

    // With tx_ref pointing at the OLD purchase → returns completed.
    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase', [
            'tx_ref' => $oldPayment->transaction_id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'completed');
});

// ─── VERIFY-PURCHASE — AUTH OBLIGATOIRE ─────────────────────────────────

it('verify-purchase requires authentication (401 for guests)', function (): void {
    $this->postJson('/api/v1/credits/verify-purchase')->assertUnauthorized();
});

// ─── PUBLIC STATUS — SANS AUTH ──────────────────────────────────────────

it('public-status returns the payment status without authentication', function (): void {
    $payment = Payment::factory()->success()->create([
        'gateway' => 'flutterwave',
        'transaction_id' => 'KH-PUBSTAT01',
    ]);

    $this->getJson('/api/v1/payments/'.$payment->transaction_id.'/public-status')
        ->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonMissing(['user_id'])
        ->assertJsonMissing(['amount']);
});

it('public-status returns unknown for malformed tx_ref', function (): void {
    // Routes constraint blocks anything that doesn't look like KH-...
    $this->getJson('/api/v1/payments/PI-NOT-OURS/public-status')
        ->assertNotFound();

    // Also unknown when the format is correct but the row doesn't exist.
    $this->getJson('/api/v1/payments/KH-DOESNOTEXIST/public-status')
        ->assertSuccessful()
        ->assertJsonPath('status', 'unknown');
});

it('public-status never leaks PII even on a real payment', function (): void {
    $user = User::factory()->create(['email' => 'sensitive@example.com']);
    $payment = Payment::factory()->success()->create([
        'user_id' => $user->id,
        'gateway' => 'flutterwave',
        'amount' => 99999,
        'phone_number' => '+237699111222',
        'transaction_id' => 'KH-LEAKCHECK1',
    ]);

    $response = $this->getJson('/api/v1/payments/'.$payment->transaction_id.'/public-status');

    $response->assertSuccessful();
    $body = (string) $response->getContent();
    expect($body)
        ->not->toContain($user->email)
        ->not->toContain($user->id)
        ->not->toContain('99999')
        ->not->toContain('+237699111222');
});
