<?php

use App\Enums\PaymentStatus;
use App\Enums\PointTransactionType;
use App\Enums\RefundStatus;
use App\Mail\RefundConfirmationMail;
use App\Models\Payment;
use App\Models\PointTransaction;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
});

it('allows admin to refund a successful payment via API', function (): void {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->success()->flutterwave()->create([
        'type' => 'credit',
        'amount' => 10000,
        'gateway_response' => ['id' => 12345, 'status' => 'successful'],
    ]);

    Http::fake([
        '*/transactions/12345/refund' => Http::response([
            'status' => 'success',
            'data' => ['id' => 'FLW-REF-001', 'status' => 'completed', 'amount_refunded' => 10000],
        ]),
    ]);

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
    $payment = Payment::factory()->pending()->flutterwave()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'reason' => 'Pending payment refund attempt',
        ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Seuls les paiements réussis peuvent être remboursés.']);
});

it('rejects duplicate full refund for same payment', function (): void {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->success()->flutterwave()->create([
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
    $response->assertJsonFragment(['message' => 'Ce paiement a déjà été remboursé intégralement.']);
});

it('supports partial refunds', function (): void {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->success()->flutterwave()->create([
        'amount' => 20000,
        'gateway_response' => ['id' => 54321],
    ]);

    Http::fake([
        '*/transactions/54321/refund' => Http::response([
            'status' => 'success',
            'data' => ['id' => 'FLW-REF-002', 'status' => 'completed', 'amount_refunded' => 5000],
        ]),
    ]);

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
    $payment = Payment::factory()->success()->flutterwave()->create([
        'type' => 'credit',
        'amount' => 5000,
        'user_id' => $customer->id,
        'gateway_response' => ['id' => 77777],
    ]);

    PointTransaction::create([
        'user_id' => $customer->id,
        'type' => PointTransactionType::PURCHASE,
        'points' => 50,
        'description' => 'Pack 50 crédits',
        'payment_id' => $payment->id,
    ]);

    Http::fake([
        '*/transactions/77777/refund' => Http::response([
            'status' => 'success',
            'data' => ['id' => 'FLW-REF-003', 'status' => 'completed', 'amount_refunded' => 5000],
        ]),
    ]);

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
    $payment = Payment::factory()->success()->flutterwave()->create([
        'gateway_response' => ['id' => 88888],
    ]);

    Http::fake([
        '*/transactions/88888/refund' => Http::response([
            'status' => 'success',
            'data' => ['id' => 'FLW-REF-004', 'status' => 'completed', 'amount_refunded' => $payment->amount],
        ]),
    ]);

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
