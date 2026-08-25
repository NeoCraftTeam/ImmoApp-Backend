<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Turnstile disabled so the request reaches the controller idempotency guard.
    config()->set('services.turnstile.secret_key', '');
    // Deterministic in-process lock semantics for the test.
    config()->set('cache.default', 'array');
    // Kpay sandbox config so a non-contended request can complete the gateway call.
    config()->set('payment.default', 'kpay');
    config()->set('payment.gateways.kpay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.kpay.redirect_url', 'https://test.app/payment/callback');
});

it('rejects a concurrent duplicate credits purchase while the lock is held', function (): void {
    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    // Simulate a first in-flight purchase holding the idempotency lock.
    $held = Cache::lock("credits:purchase:{$user->id}:{$package->id}", 15);
    expect($held->get())->toBeTrue();

    $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'callback_url' => 'keyhome://credits/callback',
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Paiement en cours de traitement, veuillez patienter.');
});

it('rejects a concurrent duplicate payment initiate while the lock is held', function (): void {
    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $held = Cache::lock("payment:initiate:{$user->id}:credit:{$package->id}", 15);
    expect($held->get())->toBeTrue();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
            'plan_id' => $package->id,
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Paiement en cours de traitement, veuillez patienter.');
});

it('releases the lock after a successful initiate so a later purchase is not blocked', function (): void {
    Event::fake();
    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_RELEASE',
            'reference' => 'KPAY-MTX-RELEASE',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_RELEASE',
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();
    $payload = ['type' => 'credit', 'plan_id' => $package->id];

    // First initiate acquires the idempotency lock then releases it in finally.
    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', $payload)
        ->assertSuccessful();

    // A second, sequential initiate must not be blocked by a stale lock: if
    // finally { $lock->release() } regressed, this would 409 instead.
    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', $payload)
        ->assertSuccessful();

    expect(Payment::query()->where('user_id', $user->id)->count())->toBe(2);
});
