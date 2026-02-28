<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Models\Ad;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\PointTransaction;
use App\Models\Setting;
use App\Models\UnlockedAd;
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

// ── PAYMENT CONTROLLER - INITIALIZE ───────────────────────────────────────────

it('initialize unlocks ad instantly when user has enough points', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    Setting::set('unlock_cost_points', 2, 'Cout unlock', 'points');

    $user = User::factory()->create(['point_balance' => 5]);
    $ad = Ad::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/payments/initialize/{$ad->id}")
        ->assertOk()
        ->assertJsonPath('status', 'unlocked');

    expect($user->fresh()->point_balance)->toBe(3);
    expect(UnlockedAd::where('user_id', $user->id)->where('ad_id', $ad->id)->exists())->toBeTrue();
});

it('initialize returns insufficient_points with packages when balance is low', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    Setting::set('unlock_cost_points', 5, 'Cout unlock', 'points');

    $user = User::factory()->create(['point_balance' => 1]);
    $ad = Ad::factory()->create();
    PointPackage::factory()->create(['is_active' => true]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/payments/initialize/{$ad->id}")
        ->assertStatus(402)
        ->assertJsonPath('status', 'insufficient_points')
        ->assertJsonStructure(['packages']);
});

it('initialize returns owner status when ad belongs to the user', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    $user = User::factory()->create(['point_balance' => 0]);
    $ad = Ad::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/payments/initialize/{$ad->id}")
        ->assertOk()
        ->assertJsonPath('status', 'owner');
});

it('initialize returns already_unlocked when ad is already unlocked', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    $user = User::factory()->create(['point_balance' => 10]);
    $ad = Ad::factory()->create();
    UnlockedAd::factory()->create(['user_id' => $user->id, 'ad_id' => $ad->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/payments/initialize/{$ad->id}")
        ->assertOk()
        ->assertJsonPath('status', 'already_unlocked');
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
    config()->set('services.fedapay.webhook_secret', $secret);

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create(['points_awarded' => 50]);

    Payment::factory()->create([
        'user_id' => $user->id,
        'transaction_id' => 'txn-credit-approved',
        'status' => PaymentStatus::PENDING,
        'type' => PaymentType::CREDIT,
        'payment_method' => PaymentMethod::FEDAPAY,
    ]);

    $body = json_encode([
        'name' => 'transaction.approved',
        'entity' => [
            'id' => 'txn-credit-approved',
            'metadata' => ['package_id' => $package->id],
        ],
    ]);

    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    $this->postJson('/api/v1/payments/webhook', json_decode($body, true), [
        'X-FedaPay-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertOk();

    expect($user->fresh()->point_balance)->toBe(50);

    $tx = PointTransaction::where('user_id', $user->id)
        ->where('type', PointTransactionType::PURCHASE->value)
        ->first();

    expect($tx)->not->toBeNull()
        ->and($tx->points)->toBe(50);
});
