<?php

/**
 * Regression tests for the credit double-issuance vulnerability.
 *
 * Root cause: verifyPurchase() lacked a DB transaction + lockForUpdate(), so it
 * could race with the FedaPay webhook and credit points twice for the same payment.
 *
 * These tests guard all three defences:
 *   1. verifyPurchase() is idempotent when called twice concurrently (application lock).
 *   2. verifyPurchase() skips crediting when the webhook already processed the payment.
 *   3. The DB unique constraint on point_transactions.payment_id rejects duplicates.
 */

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PointTransactionType;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\PointTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\FedaPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ── Helper ────────────────────────────────────────────────────────────────────

/**
 * Mock FedaPayService to return an approved transaction for any retrieve call.
 */
function mockFedaPayApproved(): void
{
    $mock = Mockery::mock(FedaPayService::class);
    $mock->shouldReceive('retrieveTransaction')
        ->andReturn(['success' => true, 'status' => 'approved']);
    app()->instance(FedaPayService::class, $mock);
}

// ── 1. Unique constraint prevents DB-level duplicate credit ───────────────────

it('the point_transactions table rejects two rows with the same payment_id', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    $user = User::factory()->create(['point_balance' => 0]);
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'status' => PaymentStatus::SUCCESS,
        'type' => PaymentType::CREDIT,
    ]);

    PointTransaction::create([
        'user_id' => $user->id,
        'type' => PointTransactionType::PURCHASE->value,
        'points' => 10,
        'description' => 'First credit',
        'payment_id' => $payment->id,
    ]);

    expect(fn () => PointTransaction::create([
        'user_id' => $user->id,
        'type' => PointTransactionType::PURCHASE->value,
        'points' => 10,
        'description' => 'Duplicate credit — must be rejected',
        'payment_id' => $payment->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

// ── 2. verifyPurchase does not re-credit when webhook already succeeded ────────

it('verify-purchase does not re-credit points when the webhook already processed the payment', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    mockFedaPayApproved();

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create(['price' => 1000, 'points_awarded' => 10]);

    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'transaction_id' => 'txn-already-webhook-processed',
        'status' => PaymentStatus::SUCCESS,
        'type' => PaymentType::CREDIT,
        'payment_method' => PaymentMethod::FEDAPAY,
        'amount' => $package->price,
    ]);

    // Simulate the PointTransaction that the webhook inserted and the resulting balance
    PointTransaction::create([
        'user_id' => $user->id,
        'type' => PointTransactionType::PURCHASE->value,
        'points' => 10,
        'description' => "Achat pack: {$package->name}",
        'payment_id' => $payment->id,
    ]);
    DB::table('users')->where('id', $user->id)->update(['point_balance' => 10]);

    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase')
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    // Balance must still be 10, not 20
    expect($user->fresh()->point_balance)->toBe(10);
    expect(PointTransaction::where('payment_id', $payment->id)->count())->toBe(1);
});

// ── 3. verifyPurchase is idempotent — calling it twice only credits once ──────

it('verify-purchase credits points exactly once even when called twice for a pending payment', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    mockFedaPayApproved();

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create(['price' => 1000, 'points_awarded' => 10]);

    Payment::factory()->create([
        'user_id' => $user->id,
        'transaction_id' => 'txn-verify-idempotent',
        'status' => PaymentStatus::PENDING,
        'type' => PaymentType::CREDIT,
        'payment_method' => PaymentMethod::FEDAPAY,
        'amount' => $package->price,
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase')
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    // Call again — simulates the frontend polling a second time
    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase')
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    expect($user->fresh()->point_balance)->toBe(10);
    expect(PointTransaction::where('user_id', $user->id)
        ->where('type', PointTransactionType::PURCHASE->value)
        ->count()
    )->toBe(1);
});

// ── 4. Pack Starter (1 000 FCFA) awards exactly 10 credits via verify route ───

it('Pack Starter at 1000 FCFA awards exactly 10 credits via verify-purchase', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    mockFedaPayApproved();

    $user = User::factory()->create(['point_balance' => 0]);
    $packStarter = PointPackage::factory()->create([
        'name' => 'Pack Starter',
        'price' => 1000,
        'points_awarded' => 10,
        'is_active' => true,
    ]);

    Payment::factory()->create([
        'user_id' => $user->id,
        'transaction_id' => 'txn-pack-starter',
        'status' => PaymentStatus::PENDING,
        'type' => PaymentType::CREDIT,
        'payment_method' => PaymentMethod::FEDAPAY,
        'amount' => $packStarter->price,
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase')
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('point_balance', 10);

    expect($user->fresh()->point_balance)->toBe(10);
});

// ── 5. Webhook followed by verify-purchase: exactly one credit of 10 points ───

it('webhook followed by verify-purchase results in exactly one credit of 10 points', function (): void {
    Setting::set('welcome_bonus_points', 0, 'Bonus bienvenue', 'points');
    $secret = 'test-webhook-secret';
    config()->set('services.fedapay.webhook_secret', $secret);

    $user = User::factory()->create(['point_balance' => 0]);
    $package = PointPackage::factory()->create(['price' => 1000, 'points_awarded' => 10]);

    Payment::factory()->create([
        'user_id' => $user->id,
        'transaction_id' => 'txn-webhook-then-verify',
        'status' => PaymentStatus::PENDING,
        'type' => PaymentType::CREDIT,
        'payment_method' => PaymentMethod::FEDAPAY,
        'amount' => $package->price,
    ]);

    // Step 1: webhook fires and credits 10 points
    $body = json_encode([
        'name' => 'transaction.approved',
        'entity' => [
            'id' => 'txn-webhook-then-verify',
            'metadata' => ['package_id' => $package->id],
        ],
    ]);
    $timestamp = (string) time();
    $sig = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    $this->postJson('/api/v1/payments/webhook', json_decode($body, true), [
        'X-FedaPay-Signature' => "t={$timestamp},v1={$sig}",
    ])->assertOk();

    expect($user->fresh()->point_balance)->toBe(10);

    // Step 2: frontend also calls verify-purchase after webhook already processed it
    $this->actingAs($user)
        ->postJson('/api/v1/credits/verify-purchase')
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    // Balance must remain 10, not 20
    expect($user->fresh()->point_balance)->toBe(10);
    expect(PointTransaction::where('user_id', $user->id)
        ->where('type', PointTransactionType::PURCHASE->value)
        ->count()
    )->toBe(1);
});
