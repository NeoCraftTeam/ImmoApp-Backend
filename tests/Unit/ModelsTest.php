<?php

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Ad;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User model', function (): void {
    it('returns full name', function (): void {
        $user = User::factory()->create([
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        expect($user->fullname)->toBe('John Doe');
    });

    it('identifies admin role', function (): void {
        $user = User::factory()->admin()->create();

        expect($user->isAdmin())->toBeTrue();
    });

    it('identifies agent role', function (): void {
        $user = User::factory()->agents()->create();

        expect($user->isAgent())->toBeTrue();
    });

    it('identifies customer role', function (): void {
        $user = User::factory()->customers()->create();

        expect($user->isCustomer())->toBeTrue();
    });

    it('agent can publish ads', function (): void {
        $agent = User::factory()->agents()->create();

        expect($agent->canPublishAds())->toBeTrue();
    });

    it('customer cannot publish ads', function (): void {
        $customer = User::factory()->customers()->create();

        expect($customer->canPublishAds())->toBeFalse();
    });

    it('identifies individual type', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::AGENT,
            'type' => UserType::INDIVIDUAL,
        ]);

        expect($user->isAnIndividual())->toBeTrue();
    });

    it('identifies agency type', function (): void {
        $agency = Agency::factory()->create();
        $user = User::factory()->create([
            'role' => UserRole::AGENT,
            'type' => UserType::AGENCY,
            'agency_id' => $agency->id,
        ]);

        expect($user->isAnAgency())->toBeTrue();
    });
});

describe('Payment model', function (): void {
    it('detects paid status', function (): void {
        $payment = Payment::factory()->create([
            'status' => 'success',
            'type' => 'unlock',
        ]);

        expect($payment->isPaid())->toBeTrue();
    });

    it('detects pending status', function (): void {
        $payment = Payment::factory()->create([
            'status' => 'pending',
            'type' => 'unlock',
        ]);

        expect($payment->isPending())->toBeTrue();
    });

    it('detects failed status', function (): void {
        $payment = Payment::factory()->create([
            'status' => 'failed',
            'type' => 'unlock',
        ]);

        expect($payment->hasFailed())->toBeTrue();
    });

    it('detects unlock type', function (): void {
        $payment = Payment::factory()->create([
            'status' => 'success',
            'type' => 'unlock',
        ]);

        expect($payment->isUnlocked())->toBeTrue();
    });

    it('detects subscription type', function (): void {
        $payment = Payment::factory()->create([
            'status' => 'success',
            'type' => 'subscription',
        ]);

        expect($payment->isSubscribed())->toBeTrue();
    });

    it('detects boost type', function (): void {
        $payment = Payment::factory()->create([
            'status' => 'success',
            'type' => 'boost',
        ]);

        expect($payment->isBoosted())->toBeTrue();
    });
});

describe('Subscription model', function (): void {
    it('detects active subscription', function (): void {
        $plan = SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'description' => 'Test',
            'price' => 10000,
            'duration_days' => 30,
            'boost_score' => 10,
            'boost_duration_days' => 7,
            'max_ads' => 10,
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $agency = Agency::factory()->create();
        $subscription = Subscription::create([
            'agency_id' => $agency->id,
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'amount_paid' => 10000,
            'auto_renew' => false,
        ]);

        expect($subscription->isActive())->toBeTrue();
    });

    it('detects expired subscription', function (): void {
        $plan = SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan-expired',
            'description' => 'Test',
            'price' => 10000,
            'duration_days' => 30,
            'boost_score' => 10,
            'boost_duration_days' => 7,
            'max_ads' => 10,
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $agency = Agency::factory()->create();
        $subscription = Subscription::create([
            'agency_id' => $agency->id,
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
            'amount_paid' => 10000,
            'auto_renew' => false,
        ]);

        expect($subscription->isExpired())->toBeTrue();
    });

    it('calculates days remaining', function (): void {
        $plan = SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan-days',
            'description' => 'Test',
            'price' => 10000,
            'duration_days' => 30,
            'boost_score' => 10,
            'boost_duration_days' => 7,
            'max_ads' => 10,
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $agency = Agency::factory()->create();
        $subscription = Subscription::create([
            'agency_id' => $agency->id,
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(10),
            'amount_paid' => 10000,
            'auto_renew' => false,
        ]);

        expect($subscription->daysRemaining())->toBeGreaterThanOrEqual(9)
            ->toBeLessThanOrEqual(10);
    });
});

describe('Ad model', function (): void {
    it('generates unique slug from title', function (): void {
        $slug1 = Ad::generateUniqueSlug('Beautiful Apartment');
        expect($slug1)->toBe('beautiful-apartment');

        Ad::withoutSyncingToSearch(function () use ($slug1): void {
            Ad::factory()->create(['slug' => $slug1]);
        });

        $slug2 = Ad::generateUniqueSlug('Beautiful Apartment');
        expect($slug2)->toBe('beautiful-apartment-1');

        Ad::withoutSyncingToSearch(function () use ($slug2): void {
            Ad::factory()->create(['slug' => $slug2]);
        });

        $slug3 = Ad::generateUniqueSlug('Beautiful Apartment');
        expect($slug3)->toBe('beautiful-apartment-2');
    });
});
