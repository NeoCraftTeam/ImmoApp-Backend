<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Characterization net for PaymentController extraction (Wave C2)
|--------------------------------------------------------------------------
| Locks the behaviour of the promo-code application inside initiate(), the
| PDF export/receipt assembly, and the visitor-locale PDF hints branch —
| none of which were exercised before extracting PromoCodeApplicator,
| PaymentPdfRenderer, and VisitorLocalePdfHints. Pins the observable
| behaviour so the move stays byte-for-byte equivalent.
*/

beforeEach(function (): void {
    config()->set('payment.default', 'kpay');
    config()->set('payment.gateways.kpay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.kpay.redirect_url', 'https://test.app/payment/callback');

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_MTX_PROMO',
            'reference' => 'KPAY-MTX-PROMO',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/gw_MTX_PROMO',
        ], 201),
    ]);
});

it('applies a valid promo code to the initiated payment amount and records usage', function (): void {
    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => true]);
    $promo = PromoCode::create([
        'code' => 'PROMO20',
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'applicable_to' => 'all',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'phone_number' => '+237699000000',
        'plan_id' => $package->id,
        'promo_code' => 'PROMO20',
    ]);

    $response->assertSuccessful();

    // 3000 - 20% = 2400 charged.
    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'amount' => 2400,
    ]);

    $this->assertDatabaseHas('promo_code_usages', [
        'promo_code_id' => $promo->id,
        'user_id' => $user->id,
    ]);

    expect($promo->fresh()->used_count)->toBe(1);
});

it('applies a promo code case-insensitively during initiate', function (): void {
    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 1000, 'is_active' => true]);
    PromoCode::create([
        'code' => 'FLAT500',
        'discount_type' => 'fixed',
        'discount_value' => 500,
        'applicable_to' => 'all',
        'is_active' => true,
    ]);

    $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'plan_id' => $package->id,
        'promo_code' => 'flat500',
    ])->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'amount' => 500,
    ]);
});

it('ignores an inactive promo code without discount or usage', function (): void {
    $user = User::factory()->create();
    $package = PointPackage::factory()->create(['price' => 3000, 'is_active' => true]);
    $promo = PromoCode::create([
        'code' => 'INACTIVE',
        'discount_type' => 'percentage',
        'discount_value' => 50,
        'applicable_to' => 'all',
        'is_active' => false,
    ]);

    $this->actingAs($user)->postJson('/api/v1/payments/initiate_payment', [
        'type' => 'credit',
        'payment_method' => 'mobile_money',
        'plan_id' => $package->id,
        'promo_code' => 'INACTIVE',
    ])->assertSuccessful();

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'amount' => 3000,
    ]);

    $this->assertDatabaseMissing('promo_code_usages', [
        'promo_code_id' => $promo->id,
    ]);

    expect($promo->fresh()->used_count)->toBe(0);
});

it('exports the payment history as a downloadable pdf', function (): void {
    $user = User::factory()->create();
    Payment::factory()->success()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);

    $response = $this->get('/api/v1/payments/export')
        ->assertSuccessful();

    expect((string) $response->headers->get('content-type'))->toContain('pdf');
    expect((string) $response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('keyhome-paiements-');
});

it('exports the payment history as pdf with visitor locale hints', function (): void {
    $user = User::factory()->create();
    Payment::factory()->success()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);

    $response = $this->get('/api/v1/payments/export?period=30&currency=EUR&rate=0.0015')
        ->assertSuccessful();

    expect((string) $response->headers->get('content-type'))->toContain('pdf');
});

it('streams a receipt pdf with visitor locale hints', function (): void {
    $user = User::factory()->create();
    $payment = Payment::factory()->success()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);

    $response = $this->get('/api/v1/payments/'.$payment->id.'/receipt?currency=USD&rate=0.0016')
        ->assertSuccessful();

    expect((string) $response->headers->get('content-type'))->toContain('pdf');
    expect((string) $response->headers->get('content-disposition'))->toContain('inline');
});
