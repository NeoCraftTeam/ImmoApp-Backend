<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Build the HTTP headers for a valid FedaPay webhook call.
 *
 * @return array<string, string>
 */
function buildWebhookHeaders(string $payload, string $secret): array
{
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FEDAPAY_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ];
}

// ── UNLOCK PAYMENT HAPPY PATH ─────────────────────────────────────────────────

it('approved webhook for unlock payment marks payment SUCCESS and creates UnlockedAd', function (): void {
    Mail::fake();

    $secret = 'test-secret-unlock';
    config()->set('services.fedapay.webhook_secret', $secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'txn-unlock-approved',
        'status' => PaymentStatus::PENDING,
        'type' => PaymentType::UNLOCK,
        'payment_method' => PaymentMethod::FEDAPAY,
        'user_id' => $user->id,
    ]);

    $payload = json_encode([
        'name' => 'transaction.approved',
        'entity' => ['id' => 'txn-unlock-approved'],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        buildWebhookHeaders($payload, $secret),
        $payload
    );

    $response->assertOk()->assertJson(['status' => 'ok']);

    expect($payment->fresh()?->status)->toBe(PaymentStatus::SUCCESS);

    expect(UnlockedAd::where('user_id', $user->id)
        ->where('ad_id', $payment->ad_id)
        ->exists()
    )->toBeTrue();
});

// ── SUBSCRIPTION PAYMENT ─────────────────────────────────────────────────────

it('approved webhook for subscription activates agency subscription', function (): void {
    Mail::fake();

    $secret = 'test-secret-subscription';
    config()->set('services.fedapay.webhook_secret', $secret);

    $owner = User::factory()->create();
    $agency = Agency::factory()->create(['owner_id' => $owner->id]);
    $plan = SubscriptionPlan::create([
        'name' => 'Starter',
        'slug' => 'starter',
        'price' => 5000,
        'price_yearly' => 50000,
        'duration_days' => 30,
        'boost_score' => 10,
        'boost_duration_days' => 30,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $payment = Payment::factory()->create([
        'transaction_id' => 'txn-subscription-approved',
        'status' => PaymentStatus::PENDING,
        'type' => PaymentType::SUBSCRIPTION,
        'payment_method' => PaymentMethod::FEDAPAY,
        'agency_id' => $agency->id,
        'user_id' => $owner->id,
    ]);

    $payload = json_encode([
        'name' => 'transaction.approved',
        'entity' => [
            'id' => 'txn-subscription-approved',
            'metadata' => [
                'payment_type' => 'subscription',
                'agency_id' => $agency->id,
                'plan_id' => $plan->id,
                'period' => 'monthly',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        buildWebhookHeaders($payload, $secret),
        $payload
    );

    $response->assertOk()->assertJson(['status' => 'ok']);

    expect($payment->fresh()?->status)->toBe(PaymentStatus::SUCCESS);

    $this->assertDatabaseHas('subscriptions', [
        'agency_id' => $agency->id,
        'subscription_plan_id' => $plan->id,
        'billing_period' => 'monthly',
    ]);
});

// ── DECLINED / CANCELED ──────────────────────────────────────────────────────

it('declined webhook marks payment as FAILED', function (): void {
    $secret = 'test-secret-declined';
    config()->set('services.fedapay.webhook_secret', $secret);

    $payment = Payment::factory()->create([
        'transaction_id' => 'txn-declined',
        'status' => PaymentStatus::PENDING,
        'type' => PaymentType::UNLOCK,
        'payment_method' => PaymentMethod::FEDAPAY,
    ]);

    $payload = json_encode([
        'name' => 'transaction.declined',
        'entity' => ['id' => 'txn-declined'],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        buildWebhookHeaders($payload, $secret),
        $payload
    );

    $response->assertOk()->assertJson(['status' => 'ok']);

    expect($payment->fresh()?->status)->toBe(PaymentStatus::FAILED);

    expect(UnlockedAd::where('ad_id', $payment->ad_id)->exists())->toBeFalse();
});

it('canceled webhook marks payment as FAILED', function (): void {
    $secret = 'test-secret-canceled';
    config()->set('services.fedapay.webhook_secret', $secret);

    $payment = Payment::factory()->create([
        'transaction_id' => 'txn-canceled',
        'status' => PaymentStatus::PENDING,
        'type' => PaymentType::UNLOCK,
        'payment_method' => PaymentMethod::FEDAPAY,
    ]);

    $payload = json_encode([
        'name' => 'transaction.canceled',
        'entity' => ['id' => 'txn-canceled'],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        buildWebhookHeaders($payload, $secret),
        $payload
    );

    $response->assertOk()->assertJson(['status' => 'ok']);

    expect($payment->fresh()?->status)->toBe(PaymentStatus::FAILED);
});

// ── IDEMPOTENCY GUARD ────────────────────────────────────────────────────────

it('second approved webhook on already-SUCCESS payment returns already_processed and does not duplicate UnlockedAd', function (): void {
    Mail::fake();

    $secret = 'test-secret-idempotent-success';
    config()->set('services.fedapay.webhook_secret', $secret);

    $user = User::factory()->create();
    $payment = Payment::factory()->create([
        'transaction_id' => 'txn-idempotent-success',
        'status' => PaymentStatus::SUCCESS,
        'type' => PaymentType::UNLOCK,
        'payment_method' => PaymentMethod::FEDAPAY,
        'user_id' => $user->id,
    ]);

    UnlockedAd::create([
        'ad_id' => $payment->ad_id,
        'user_id' => $user->id,
        'payment_id' => $payment->id,
        'unlocked_at' => now(),
    ]);

    $payload = json_encode([
        'name' => 'transaction.approved',
        'entity' => ['id' => 'txn-idempotent-success'],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        buildWebhookHeaders($payload, $secret),
        $payload
    );

    $response->assertOk()->assertJson(['status' => 'already_processed']);

    expect(UnlockedAd::where('ad_id', $payment->ad_id)->count())->toBe(1);
});

it('second declined webhook on already-FAILED payment returns already_processed', function (): void {
    $secret = 'test-secret-idempotent-failed';
    config()->set('services.fedapay.webhook_secret', $secret);

    $payment = Payment::factory()->create([
        'transaction_id' => 'txn-idempotent-failed',
        'status' => PaymentStatus::FAILED,
        'type' => PaymentType::UNLOCK,
        'payment_method' => PaymentMethod::FEDAPAY,
    ]);

    $payload = json_encode([
        'name' => 'transaction.declined',
        'entity' => ['id' => 'txn-idempotent-failed'],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        buildWebhookHeaders($payload, $secret),
        $payload
    );

    $response->assertOk()->assertJson(['status' => 'already_processed']);
});

// ── SECURITY GUARDS ──────────────────────────────────────────────────────────

it('webhook with expired timestamp (> 5 min) is rejected with 401', function (): void {
    $secret = 'test-secret-expired';
    config()->set('services.fedapay.webhook_secret', $secret);

    $payload = json_encode([
        'name' => 'transaction.approved',
        'entity' => ['id' => 'txn-expired'],
    ], JSON_THROW_ON_ERROR);

    $expiredTimestamp = (string) (time() - 400);
    $signature = hash_hmac('sha256', $expiredTimestamp.'.'.$payload, $secret);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FEDAPAY_SIGNATURE' => "t={$expiredTimestamp},v1={$signature}",
        ],
        $payload
    );

    $response->assertUnauthorized();
});

it('webhook without FEDAPAY_WEBHOOK_SECRET configured returns 500', function (): void {
    config()->set('services.fedapay.webhook_secret', '');

    $payload = json_encode([
        'name' => 'transaction.approved',
        'entity' => ['id' => 'txn-no-secret'],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FEDAPAY_SIGNATURE' => 'any-signature',
        ],
        $payload
    );

    $response->assertStatus(500);
});

it('webhook for unknown transaction_id returns 404', function (): void {
    $secret = 'test-secret-notfound';
    config()->set('services.fedapay.webhook_secret', $secret);

    $payload = json_encode([
        'name' => 'transaction.approved',
        'entity' => ['id' => 'txn-does-not-exist'],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/api/v1/payments/webhook',
        [],
        [],
        [],
        buildWebhookHeaders($payload, $secret),
        $payload
    );

    $response->assertNotFound();
});
