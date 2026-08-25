<?php

declare(strict_types=1);

use App\Enums\PaymentType;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| SubscriptionController payment contract — characterization
|--------------------------------------------------------------------------
| Pins the blast radius of the C1 refactor (BillingPeriod amount ternary,
| the InitiateSubscriptionPayment action, and the upgrade/downgrade
| validation) BEFORE any code moves. These surfaces are not covered by the
| existing suites: the amount charged per billing period, the exact gateway
| `metadata` (action / subscription_id per flow), and the inline-validation
| 422s. Every assertion must stay green with byte-identical behavior after
| the extraction.
*/

function fakeKpayForContract(): void
{
    config()->set('payment.default', 'kpay');
    config()->set('payment.gateways.kpay.api_key', 'pk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.api_secret', 'sk_sandbox_test_fake');
    config()->set('payment.gateways.kpay.webhook_secret', 'whsec_sandbox_test_secret_123');
    config()->set('payment.gateways.kpay.redirect_url', 'https://test.app/payment/callback');

    Http::fake([
        'admin.kpay.site/*' => Http::response([
            'id' => 'pay_SUB_TEST',
            'reference' => 'KPAY-SUB-TEST',
            'gatewayUrl' => 'https://admin.kpay.site/gateway/pay_SUB_TEST',
            'status' => 'PENDING',
        ], 201),
    ]);
}

/**
 * @return array<string, mixed>
 */
function sentSubscriptionMetadata(): array
{
    $captured = [];

    Http::assertSent(function (Request $request) use (&$captured): bool {
        if (!str_contains($request->url(), '/api/v1/payments/init')) {
            return false;
        }
        $captured = $request->data()['metadata'] ?? [];

        return true;
    });

    return $captured;
}

beforeEach(function (): void {
    fakeKpayForContract();
    $this->agency = Agency::factory()->create();
    $this->owner = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->agency->update(['owner_id' => $this->owner->id]);
});

it('subscribe charges the monthly price and omits action / subscription_id from the metadata', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create();

    $this->actingAs($this->owner)
        ->postJson('/api/v1/subscriptions/subscribe', [
            'plan_id' => $plan->id,
            'billing_period' => 'monthly',
        ])->assertOk()
        ->assertJsonStructure(['payment_url', 'message']);

    $payment = Payment::query()->where('type', PaymentType::SUBSCRIPTION)->sole();
    expect($payment->amount)->toBe((int) $plan->price)
        ->and($payment->agency_id)->toBe($this->agency->id)
        ->and($payment->plan_id)->toBe($plan->id)
        ->and($payment->period)->toBe('monthly');

    $metadata = sentSubscriptionMetadata();
    expect($metadata['payment_type'])->toBe('subscription')
        ->and($metadata['agency_id'])->toBe($this->agency->id)
        ->and($metadata['plan_id'])->toBe($plan->id)
        ->and($metadata['period'])->toBe('monthly')
        ->and($metadata)->not->toHaveKey('action')
        ->and($metadata)->not->toHaveKey('subscription_id');
});

it('subscribe charges the yearly price when the yearly period is chosen', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create();

    $this->actingAs($this->owner)
        ->postJson('/api/v1/subscriptions/subscribe', [
            'plan_id' => $plan->id,
            'billing_period' => 'yearly',
        ])->assertOk();

    $payment = Payment::query()->where('type', PaymentType::SUBSCRIPTION)->sole();
    expect($payment->amount)->toBe((int) $plan->price_yearly)
        ->and($payment->period)->toBe('yearly');
});

it('renew tags the metadata with action=renew and the subscription id', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create();
    $subscription = Subscription::factory()->expired()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $plan->id,
        'billing_period' => 'monthly',
    ]);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/subscriptions/renew')
        ->assertStatus(202);

    $payment = Payment::query()->where('type', PaymentType::SUBSCRIPTION)->sole();
    expect($payment->amount)->toBe((int) $plan->price)
        ->and($payment->plan_id)->toBe($plan->id)
        ->and($payment->period)->toBe('monthly');

    $metadata = sentSubscriptionMetadata();
    expect($metadata['action'])->toBe('renew')
        ->and($metadata['subscription_id'])->toBe($subscription->id)
        ->and($metadata['plan_id'])->toBe($plan->id)
        ->and($metadata['period'])->toBe('monthly');
});

it('upgrade tags the metadata with action=upgrade, the subscription id and the new plan', function (): void {
    $basicPlan = SubscriptionPlan::factory()->basic()->create();
    $premiumPlan = SubscriptionPlan::factory()->premium()->create();
    $subscription = Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $basicPlan->id,
        'billing_period' => 'monthly',
    ]);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/subscriptions/upgrade', [
            'plan_id' => $premiumPlan->id,
            'billing_period' => 'yearly',
        ])->assertStatus(202);

    $payment = Payment::query()->where('type', PaymentType::SUBSCRIPTION)->sole();
    expect($payment->amount)->toBe((int) $premiumPlan->price_yearly)
        ->and($payment->plan_id)->toBe($premiumPlan->id)
        ->and($payment->period)->toBe('yearly');

    $metadata = sentSubscriptionMetadata();
    expect($metadata['action'])->toBe('upgrade')
        ->and($metadata['subscription_id'])->toBe($subscription->id)
        ->and($metadata['plan_id'])->toBe($premiumPlan->id);
});

it('rejects a plan-change request with a missing plan_id', function (string $endpoint): void {
    $this->actingAs($this->owner)
        ->postJson("/api/v1/subscriptions/{$endpoint}", ['billing_period' => 'monthly'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('plan_id');
})->with(['upgrade', 'downgrade']);

it('rejects a plan-change request with an invalid billing_period', function (string $endpoint): void {
    $plan = SubscriptionPlan::factory()->premium()->create();

    $this->actingAs($this->owner)
        ->postJson("/api/v1/subscriptions/{$endpoint}", [
            'plan_id' => $plan->id,
            'billing_period' => 'weekly',
        ])->assertStatus(422)
        ->assertJsonValidationErrors('billing_period');
})->with(['upgrade', 'downgrade']);

it('upgrade returns 404 when the agency has no active subscription', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create();

    $this->actingAs($this->owner)
        ->postJson('/api/v1/subscriptions/upgrade', [
            'plan_id' => $plan->id,
            'billing_period' => 'monthly',
        ])->assertNotFound()
        ->assertJsonPath('message', 'Aucun abonnement actif.');
});

it('upgrade returns 422 when the target plan matches the current plan', function (): void {
    $plan = SubscriptionPlan::factory()->premium()->create();
    Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $plan->id,
        'billing_period' => 'monthly',
    ]);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/subscriptions/upgrade', [
            'plan_id' => $plan->id,
            'billing_period' => 'monthly',
        ])->assertStatus(422)
        ->assertJsonPath('message', 'Vous êtes déjà abonné à ce plan.');
});

it('upgrade returns 422 when the target plan is inactive', function (): void {
    $basicPlan = SubscriptionPlan::factory()->basic()->create();
    $inactivePlan = SubscriptionPlan::factory()->premium()->create(['is_active' => false]);
    Subscription::factory()->active()->create([
        'agency_id' => $this->agency->id,
        'subscription_plan_id' => $basicPlan->id,
        'billing_period' => 'monthly',
    ]);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/subscriptions/upgrade', [
            'plan_id' => $inactivePlan->id,
            'billing_period' => 'monthly',
        ])->assertStatus(422)
        ->assertJsonPath('message', 'Ce plan n\'est plus disponible.');
});
