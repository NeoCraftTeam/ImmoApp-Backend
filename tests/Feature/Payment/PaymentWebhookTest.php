<?php

declare(strict_types=1);

use App\Actions\HandlePostPaymentActions;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('does not double-activate a subscription when post-payment is fired twice', function (): void {
    Event::fake();

    /** @var User $user */
    $user = User::factory()->create();
    $agency = Agency::factory()->for($user, 'owner')->create();

    /** @var SubscriptionPlan $plan */
    $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

    /** @var Payment $payment */
    $payment = Payment::factory()->create([
        'user_id' => $user->id,
        'agency_id' => $agency->id,
        'plan_id' => $plan->id,
        'period' => 'monthly',
        'type' => PaymentType::SUBSCRIPTION->value,
        'status' => PaymentStatus::SUCCESS->value,
    ]);

    $action = app(HandlePostPaymentActions::class);

    // Simulate webhook → verify race: HandlePostPaymentActions runs twice.
    $action->execute($payment, []);
    $action->execute($payment, []);

    expect(
        Subscription::query()->where('payment_id', $payment->id)->count()
    )->toBe(1);
});
