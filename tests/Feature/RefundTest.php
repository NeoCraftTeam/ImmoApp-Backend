<?php

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Enums\PointTransactionType;
use App\Enums\RefundStatus;
use App\Mail\RefundConfirmationMail;
use App\Models\Payment;
use App\Models\PointTransaction;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payment\StripePaymentService;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
});

it('allows admin to refund a successful payment via API', function (): void {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->success()->stripe()->create([
        'type' => 'credit',
        'amount' => 10000,
        'gateway_response' => ['id' => 'pi_refund001', 'status' => 'succeeded'],
    ]);

    $spy = Mockery::mock(PaymentGatewayInterface::class);
    $spy->shouldReceive('refund')
        ->once()
        ->with('pi_refund001', 10000.0)
        ->andReturn([
            'refund_id' => 're_refund001',
            'status' => 'succeeded',
            'amount_refunded' => 10000.0,
            'raw' => ['id' => 're_refund001', 'status' => 'succeeded'],
        ]);

    $this->app->instance(StripePaymentService::class, $spy);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'reason' => 'Client mécontent du service fourni',
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('refund.status', 'completed');
    $response->assertJsonPath('refund.amount', '10000.00');

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::REFUNDED);

    expect(Refund::where('payment_id', $payment->id)->count())->toBe(1);
});

it('rejects refund requests from non-admin users', function (): void {
    $customer = User::factory()->create(['role' => 'customer']);
    $payment = Payment::factory()->success()->create();

    $response = $this->actingAs($customer, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'reason' => 'I want my money back',
        ]);

    $response->assertForbidden();
});

it('rejects unauthenticated refund requests', function (): void {
    $payment = Payment::factory()->success()->create();

    $response = $this->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
        'reason' => 'Test refund',
    ]);

    $response->assertUnauthorized();
});

it('rejects refund for non-success payment', function (): void {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->pending()->stripe()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'reason' => 'Pending payment refund attempt',
        ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Le remboursement n\'a pas pu être traité. Vérifiez les conditions requises.']);
});

it('rejects duplicate full refund for same payment', function (): void {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->success()->stripe()->create([
        'gateway_response' => ['id' => 99999],
    ]);

    Refund::factory()->completed()->create([
        'payment_id' => $payment->id,
        'user_id' => $payment->user_id,
        'amount' => $payment->amount,
        'is_partial' => false,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'reason' => 'Second refund attempt',
        ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Le remboursement n\'a pas pu être traité. Vérifiez les conditions requises.']);
});

it('supports partial refunds', function (): void {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->success()->stripe()->create([
        'amount' => 20000,
        'gateway_response' => ['id' => 'pi_refund002', 'status' => 'succeeded'],
    ]);

    $spy = Mockery::mock(PaymentGatewayInterface::class);
    $spy->shouldReceive('refund')
        ->once()
        ->with('pi_refund002', 5000.0)
        ->andReturn([
            'refund_id' => 're_refund002',
            'status' => 'succeeded',
            'amount_refunded' => 5000.0,
            'raw' => ['id' => 're_refund002', 'status' => 'succeeded'],
        ]);

    $this->app->instance(StripePaymentService::class, $spy);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'reason' => 'Partial service only',
            'amount' => 5000,
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('refund.is_partial', true);

    // Payment should NOT be marked as REFUNDED for partial refunds
    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::SUCCESS);
});

it('validates refund request fields', function (): void {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->success()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['reason']);
});

it('lists refunds for a payment', function (): void {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->success()->create();

    Refund::factory()->completed()->count(2)->create([
        'payment_id' => $payment->id,
        'user_id' => $payment->user_id,
        'processed_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/admin/payments/{$payment->id}/refunds");

    $response->assertSuccessful();
    $response->assertJsonCount(2, 'data');
});

it('reverses credit points on full refund', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'credits');

    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create(['point_balance' => 100]);
    $payment = Payment::factory()->success()->stripe()->create([
        'type' => 'credit',
        'amount' => 5000,
        'user_id' => $customer->id,
        'gateway_response' => ['id' => 'pi_refund003', 'status' => 'succeeded'],
    ]);

    PointTransaction::create([
        'user_id' => $customer->id,
        'type' => PointTransactionType::PURCHASE,
        'points' => 50,
        'description' => 'Pack 50 crédits',
        'payment_id' => $payment->id,
    ]);

    $spy = Mockery::mock(PaymentGatewayInterface::class);
    $spy->shouldReceive('refund')
        ->once()
        ->with('pi_refund003', 5000.0)
        ->andReturn([
            'refund_id' => 're_refund003',
            'status' => 'succeeded',
            'amount_refunded' => 5000.0,
            'raw' => ['id' => 're_refund003', 'status' => 'succeeded'],
        ]);

    $this->app->instance(StripePaymentService::class, $spy);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'reason' => 'Credit reversal test',
        ])
        ->assertSuccessful();

    $customer->refresh();
    expect($customer->point_balance)->toBe(50);

    $refundTx = PointTransaction::where('user_id', $customer->id)
        ->where('type', PointTransactionType::REFUND)
        ->first();
    expect($refundTx)->not->toBeNull();
    expect($refundTx->points)->toBe(-50);
});

it('sends confirmation email after successful refund', function (): void {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->success()->stripe()->create([
        'gateway_response' => ['id' => 'pi_refund004', 'status' => 'succeeded'],
    ]);

    $spy = Mockery::mock(PaymentGatewayInterface::class);
    $spy->shouldReceive('refund')
        ->once()
        ->with('pi_refund004', (float) $payment->amount)
        ->andReturn([
            'refund_id' => 're_refund004',
            'status' => 'succeeded',
            'amount_refunded' => (float) $payment->amount,
            'raw' => ['id' => 're_refund004', 'status' => 'succeeded'],
        ]);

    $this->app->instance(StripePaymentService::class, $spy);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'reason' => 'Email test refund',
        ])
        ->assertSuccessful();

    Mail::assertQueued(RefundConfirmationMail::class);
});

it('creates refund model with correct relationships', function (): void {
    $refund = Refund::factory()->completed()->create();

    expect($refund->payment)->toBeInstanceOf(Payment::class);
    expect($refund->user)->toBeInstanceOf(User::class);
    expect($refund->status)->toBe(RefundStatus::Completed);
});

/*
|--------------------------------------------------------------------------
| Stripe gateway routing — regression guard
|--------------------------------------------------------------------------
|
| Before the fix, `RefundService::resolveGateway()` did not map
| `stripe` to a gateway implementation and threw
| « Gateway non supporté: stripe » for every card payment, blocking
| admin refunds in production. These tests lock in the new behaviour:
|   1. card payments resolve to a Stripe gateway implementation
|   2. the canonical gateway transaction id is the Stripe PaymentIntent id
|   3. the refund lifecycle (DB row + side-effects + email) runs to
|      completion when the Stripe SDK call succeeds
|
| We bind a fake `PaymentGatewayInterface` over `StripePaymentService` in
| the container so we never hit the real Stripe API in tests.
*/
it('routes Stripe payment refunds via StripePaymentService', function (): void {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();

    $payment = Payment::factory()->success()->create([
        'type' => 'credit',
        'amount' => 1000,
        'gateway' => 'stripe',
        'payment_method' => 'card',
        'transaction_id' => 'KH-STRIPE-001',
        'user_id' => $customer->id,
        // Mirrors what `StripePaymentService::normaliseIntent()` persists:
        // the `id` key is the Stripe PaymentIntent id (`pi_…`).
        'gateway_response' => [
            'id' => 'pi_3OabcdEFGHijkl1234567890',
            'status' => 'succeeded',
            'amount' => 152, // 1000 XAF → 152 EUR cents at peg
            'currency' => 'eur',
        ],
    ]);

    // Spy that captures arguments and returns a successful refund payload
    // matching `PaymentGatewayInterface::refund()` contract.
    $spy = Mockery::mock(PaymentGatewayInterface::class);
    $spy->shouldReceive('refund')
        ->once()
        ->with('pi_3OabcdEFGHijkl1234567890', 1000.0)
        ->andReturn([
            'refund_id' => 're_3OabcdEFGH001',
            'status' => 'succeeded',
            'amount_refunded' => 1000.0,
            'raw' => ['id' => 're_3OabcdEFGH001', 'status' => 'succeeded'],
        ]);

    $this->app->instance(StripePaymentService::class, $spy);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'reason' => 'Carte refusée par le client après livraison',
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('refund.status', 'completed');

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::REFUNDED);

    $refund = Refund::where('payment_id', $payment->id)->first();
    expect($refund)->not->toBeNull();
    expect($refund->gateway_refund_id)->toBe('re_3OabcdEFGH001');
});

it('falls back to Payment.transaction_id when Stripe gateway_response lacks id', function (): void {
    // Legacy edge case: an old Stripe payment row may have an empty
    // `gateway_response` (e.g. failed first sync). The refund flow must
    // still succeed by passing the local `tx_ref` to the gateway, which
    // `StripePaymentService::resolveStripeIntentId()` accepts natively.
    $admin = User::factory()->admin()->create();

    $payment = Payment::factory()->success()->create([
        'type' => 'credit',
        'amount' => 5000,
        'gateway' => 'stripe',
        'payment_method' => 'card',
        'transaction_id' => 'KH-LEGACY-FALLBACK',
        'gateway_response' => [], // intentionally empty
    ]);

    $spy = Mockery::mock(PaymentGatewayInterface::class);
    $spy->shouldReceive('refund')
        ->once()
        ->with('KH-LEGACY-FALLBACK', 5000.0)
        ->andReturn([
            'refund_id' => 're_legacy_001',
            'status' => 'succeeded',
            'amount_refunded' => 5000.0,
            'raw' => ['id' => 're_legacy_001'],
        ]);

    $this->app->instance(StripePaymentService::class, $spy);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'reason' => 'Test fallback tx_ref',
        ])
        ->assertSuccessful();
});
