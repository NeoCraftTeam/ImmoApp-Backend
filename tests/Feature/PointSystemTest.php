<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\PointTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── POINT SERVICE ──────────────────────────────────────────────────────────────

it('credits points to a user and records the transaction', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    $user = User::factory()->create(['point_balance' => 0]);

    $service = app(PointService::class);
    $service->credit($user, 10, PointTransactionType::BONUS, 'Test bonus');

    expect($user->fresh()->point_balance)->toBe(10);

    $transaction = PointTransaction::where('user_id', $user->id)
        ->where('type', PointTransactionType::BONUS->value)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->points)->toBe(10);
});

it('deducts points from a user and records the transaction', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    $user = User::factory()->create(['point_balance' => 10]);
    $service = app(PointService::class);

    $service->deduct($user, 2, 'Unlock test');

    expect($user->fresh()->point_balance)->toBe(8);

    $transaction = PointTransaction::where('user_id', $user->id)
        ->where('type', PointTransactionType::UNLOCK->value)
        ->latest()
        ->first();

    expect($transaction->points)->toBe(-2);
});

it('throws when deducting more than the available balance', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    $user = User::factory()->create(['point_balance' => 1]);
    $service = app(PointService::class);

    expect(fn () => $service->deduct($user, 5, 'Too expensive'))->toThrow(\RuntimeException::class);

    expect($user->fresh()->point_balance)->toBe(1);
});

it('hasEnough returns true when balance is sufficient', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    $user = User::factory()->create(['point_balance' => 5]);
    expect(app(PointService::class)->hasEnough($user, 5))->toBeTrue();
});

it('hasEnough returns false when balance is insufficient', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    $user = User::factory()->create(['point_balance' => 1]);
    expect(app(PointService::class)->hasEnough($user, 2))->toBeFalse();
});

// ── CREDIT CONTROLLER ──────────────────────────────────────────────────────────

it('packages endpoint returns only active packages', function (): void {
    PointPackage::factory()->count(2)->create(['is_active' => true]);
    PointPackage::factory()->inactive()->create();

    $response = $this->getJson('/api/v1/credits/packages')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('balance endpoint returns current user point balance', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');

    $user = User::factory()->create(['point_balance' => 42]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/credits/balance')
        ->assertOk()
        ->assertJsonPath('point_balance', 42);
});

it('balance endpoint requires authentication', function (): void {
    $this->getJson('/api/v1/credits/balance')->assertUnauthorized();
});

// ── WEBHOOK - CREDIT PURCHASE ──────────────────────────────────────────────────

it('approved webhook for CREDIT payment credits points to the user', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');

    $secret = 'test-webhook-secret';
    config()->set('payment.gateways.flutterwave.webhook_secret', $secret);
    config()->set('payment.gateways.flutterwave.secret_key', 'FLWSECK_TEST-fake');

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create(['points_awarded' => 50, 'price' => 5000]);

    Payment::factory()->create([
        'user_id' => $user->id,
        'transaction_id' => 'KH-CREDITAPPROVED',
        'status' => PaymentStatus::PENDING,
        'type' => PaymentType::CREDIT,
        'payment_method' => PaymentMethod::FLUTTERWAVE,
        'gateway' => 'flutterwave',
        'amount' => 5000,
        'plan_id' => $package->id,
    ]);

    $payload = json_encode([
        'event' => 'charge.completed',
        'data' => [
            'status' => 'successful',
            'tx_ref' => 'KH-CREDITAPPROVED',
            'amount' => 5000,
            'currency' => 'XAF',
            'meta' => ['package_id' => $package->id],
        ],
    ]);

    $this->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => $secret,
    ], $payload)->assertOk();

    expect($user->fresh()->point_balance)->toBe(50);

    $tx = PointTransaction::where('user_id', $user->id)
        ->where('type', PointTransactionType::PURCHASE->value)
        ->first();

    expect($tx)->not->toBeNull()
        ->and($tx->points)->toBe(50);
});

// ── VERIFY CREDIT PURCHASE ─────────────────────────────────────────────────────

it('verify-purchase returns completed when credit payment is already successful', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');

    $user = User::factory()->create(['point_balance' => 50]);

    Payment::factory()->create([
        'user_id' => $user->id,
        'transaction_id' => 'txn-verify-completed',
        'status' => PaymentStatus::SUCCESS,
        'type' => PaymentType::CREDIT,
        'payment_method' => PaymentMethod::FLUTTERWAVE,
        'gateway' => 'flutterwave',
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase')
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('point_balance', 50);
});

it('verify-purchase returns not_found when no credit payment exists', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');

    $user = User::factory()->create(['point_balance' => 0]);

    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase')
        ->assertNotFound()
        ->assertJsonPath('status', 'not_found');
});

it('verify-purchase requires authentication', function (): void {
    $this->postJson('/api/v1/credits/verify-purchase')->assertUnauthorized();
});
