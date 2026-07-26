<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Agency;
use App\Models\PointPackage;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('payment.default', 'kpay');
    config()->set('payment.gateways.kpay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.kpay.redirect_url', 'https://test.app/payment/callback');
});

// NB : l'enforcement Turnstile sur le chemin web (session) est couvert par
// un test unitaire déterministe du trait
// {@see tests/Unit/EnsuresCreditPurchasePassesTurnstileTest.php} — simuler
// une session Sanctum stateful dans un test HTTP est trop fragile.

it('skips turnstile for credit initiate from a stateless mobile request', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_MOBILE',
            'reference' => 'KPAY-MTX-MOBILE',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_MOBILE',
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    // Pas de session (bearer Sanctum) : le mobile ne peut pas fournir de
    // token Turnstile, la vérification est sautée.
    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
            'plan_id' => $package->id,
        ])
        ->assertSuccessful();
});

it('skips turnstile for credit initiate from a native mobile app (X-KeyHome-Client)', function (string $client): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_MOBILE_HDR',
            'reference' => 'KPAY-MTX-MOBILE-HDR',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_MOBILE_HDR',
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    // Native app sends `X-KeyHome-Client` and no turnstile_token — the
    // backend must never block it even when a real secret is configured.
    $this->actingAs($user)
        ->withHeaders(['X-KeyHome-Client' => $client])
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
            'plan_id' => $package->id,
        ])
        ->assertSuccessful();
})->with([
    'visitors' => 'keyhome-mobile-visitors',
    'owners' => 'keyhome-mobile-owners',
]);

it('skips turnstile for credits purchase from a native mobile app (X-KeyHome-Client)', function (string $client): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_MOBILE_HDR2',
            'reference' => 'KPAY-MTX-MOBILE-HDR2',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_MOBILE_HDR2',
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeaders(['X-KeyHome-Client' => $client])
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'callback_url' => 'keyhome://credits/callback',
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['payment_url', 'tx_ref', 'gateway']);
})->with([
    'visitors' => 'keyhome-mobile-visitors',
    'owners' => 'keyhome-mobile-owners',
]);

it('allows credit initiate without turnstile when turnstile is not configured', function (): void {
    config()->set('services.turnstile.secret_key', '');

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_TURNSTILE',
            'reference' => 'KPAY-MTX-TURNSTILE',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_TURNSTILE',
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
            'plan_id' => $package->id,
        ])
        ->assertSuccessful();
});

it('does not require turnstile for subscription initiate when turnstile is configured', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_TURNSTILE',
            'reference' => 'KPAY-MTX-TURNSTILE',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_TURNSTILE',
        ], 201),
    ]);

    $agency = Agency::factory()->create();
    $agentUser = User::factory()->create([
        'role' => UserRole::AGENT,
        'type' => UserType::AGENCY,
        'agency_id' => $agency->id,
    ]);

    /** @var SubscriptionPlan $plan */
    $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

    $this->actingAs($agentUser)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'subscription',
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'period' => 'monthly',
            'payment_method' => 'mobile_money',
            'phone_number' => '+237699000000',
        ])
        ->assertSuccessful();
});

it('passes credit initiate with valid turnstile token when turnstile is configured', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_TURNSTILE',
            'reference' => 'KPAY-MTX-TURNSTILE',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_TURNSTILE',
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/payments/initiate_payment', [
            'type' => 'credit',
            'plan_id' => $package->id,
            'turnstile_token' => 'test-token-from-widget',
        ])
        ->assertSuccessful();
});

it('skips turnstile for credits purchase from a stateless mobile request', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_MOBILE',
            'reference' => 'KPAY-MTX-MOBILE',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_MOBILE',
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    // Callback deep-link mobile accepté par FrontendRedirectGuard.
    $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'callback_url' => 'keyhome://credits/callback',
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['payment_url', 'tx_ref', 'gateway']);
});

it('n\'envoie jamais un callback deep-link comme returnUrl à la passerelle', function (): void {
    config()->set('services.turnstile.secret_key', '');
    config()->set('app.frontend_url', 'https://keyhome.app');

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_DEEPLINK',
            'reference' => 'KPAY-MTX-DEEPLINK',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_DEEPLINK',
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'callback_url' => 'keyhome://credits/callback',
        ])
        ->assertSuccessful();

    // La passerelle DOIT recevoir une URL http(s) (jamais le deep-link mobile),
    // sinon Kpay rejette returnUrl/cancelUrl.
    Http::assertSent(function ($request): bool {
        $body = $request->data();
        $returnUrl = (string) ($body['returnUrl'] ?? '');

        return str_starts_with($returnUrl, 'http://') || str_starts_with($returnUrl, 'https://');
    });
});

it('rejects a credits purchase whose callback_url host is not allowed', function (): void {
    config()->set('services.turnstile.secret_key', '');
    config()->set('app.frontend_url', 'https://keyhome.app');
    config()->set('app.oauth_allowed_redirect_hosts', '');

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    // Un host arbitraire doit être refusé par FrontendRedirectGuard côté
    // contrôleur (422 « URL de retour non autorisée »), même si la
    // validation du FormRequest laisse passer la chaîne.
    $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'callback_url' => 'https://evil.example.com/steal',
        ])
        ->assertUnprocessable();
});

it('allows credits purchase endpoint with valid turnstile token when configured', function (): void {
    config()->set('services.turnstile.secret_key', 'real-test-secret-not-dummy-placeholder');

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_TURNSTILE',
            'reference' => 'KPAY-MTX-TURNSTILE',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_TURNSTILE',
        ], 201),
    ]);

    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/v1/credits/purchase/{$package->id}", [
            'turnstile_token' => 'test-token-from-widget',
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['payment_url', 'tx_ref', 'gateway']);
});
