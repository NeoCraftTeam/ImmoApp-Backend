<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\RentPayment;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function makeOwnedLease(User $owner): LeaseContract
{
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    return LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
    ]);
}

it('lists rent payments for the landlord owning the lease', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);
    $lease = makeOwnedLease($owner);

    RentPayment::factory()->count(3)->create([
        'lease_contract_id' => $lease->id,
        'recorded_by_user_id' => $owner->id,
    ]);

    $response = $this->getJson("/api/v1/my/lease-contracts/{$lease->id}/rent-payments")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('meta.total'))->toBe(3);
});

it('forbids listing rent payments on another landlord\'s lease', function (): void {
    $owner = User::factory()->agents()->create();
    $other = User::factory()->agents()->create();
    Sanctum::actingAs($other);
    $lease = makeOwnedLease($owner);

    $this->getJson("/api/v1/my/lease-contracts/{$lease->id}/rent-payments")
        ->assertForbidden();
});

it('records a rent payment and normalises period_month to the first of month', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);
    $lease = makeOwnedLease($owner);

    $response = $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/rent-payments", [
        'period_month' => '2026-05-17', // mid-month — controller must normalise to 2026-05-01
        'amount' => 150000,
        'payment_method' => 'mobile_money',
        'received_at' => '2026-05-20',
        'notes' => 'Orange Money ref OM-12345',
    ])->assertCreated();

    expect($response->json('data.period_month'))->toBe('2026-05-01')
        ->and($response->json('data.amount'))->toBe(150000)
        ->and($response->json('data.payment_method'))->toBe('mobile_money');

    $this->assertDatabaseHas('rent_payments', [
        'lease_contract_id' => $lease->id,
        'amount' => 150000,
        'payment_method' => 'mobile_money',
        'recorded_by_user_id' => $owner->id,
    ]);
});

it('rejects rent payments with a future received_at', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);
    $lease = makeOwnedLease($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/rent-payments", [
        'period_month' => '2026-05-01',
        'amount' => 150000,
        'payment_method' => 'cash',
        'received_at' => now()->addDays(7)->toDateString(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['received_at']);
});

it('rejects rent payments with invalid payment_method', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);
    $lease = makeOwnedLease($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/rent-payments", [
        'period_month' => '2026-05-01',
        'amount' => 150000,
        'payment_method' => 'crypto',
        'received_at' => '2026-05-20',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['payment_method']);
});

it('rejects rent payments with non-positive amount', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);
    $lease = makeOwnedLease($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/rent-payments", [
        'period_month' => '2026-05-01',
        'amount' => 0,
        'payment_method' => 'cash',
        'received_at' => '2026-05-20',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('forbids storing a rent payment on another landlord\'s lease', function (): void {
    $owner = User::factory()->agents()->create();
    $other = User::factory()->agents()->create();
    Sanctum::actingAs($other);
    $lease = makeOwnedLease($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/rent-payments", [
        'period_month' => '2026-05-01',
        'amount' => 150000,
        'payment_method' => 'cash',
        'received_at' => '2026-05-20',
    ])->assertForbidden();
});

it('updates a rent payment', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);
    $lease = makeOwnedLease($owner);

    $payment = RentPayment::factory()->create([
        'lease_contract_id' => $lease->id,
        'recorded_by_user_id' => $owner->id,
        'amount' => 100000,
    ]);

    $this->putJson("/api/v1/my/rent-payments/{$payment->id}", [
        'amount' => 175000,
        'notes' => 'Corrected amount',
    ])->assertOk();

    $this->assertDatabaseHas('rent_payments', [
        'id' => $payment->id,
        'amount' => 175000,
        'notes' => 'Corrected amount',
    ]);
});

it('forbids updating a rent payment on another landlord\'s lease', function (): void {
    $owner = User::factory()->agents()->create();
    $other = User::factory()->agents()->create();
    Sanctum::actingAs($other);
    $lease = makeOwnedLease($owner);

    $payment = RentPayment::factory()->create([
        'lease_contract_id' => $lease->id,
        'recorded_by_user_id' => $owner->id,
    ]);

    $this->putJson("/api/v1/my/rent-payments/{$payment->id}", [
        'amount' => 1,
    ])->assertForbidden();
});

it('deletes a rent payment', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);
    $lease = makeOwnedLease($owner);

    $payment = RentPayment::factory()->create([
        'lease_contract_id' => $lease->id,
        'recorded_by_user_id' => $owner->id,
    ]);

    $this->deleteJson("/api/v1/my/rent-payments/{$payment->id}")->assertOk();

    $this->assertDatabaseMissing('rent_payments', ['id' => $payment->id]);
});

it('forbids deleting a rent payment on another landlord\'s lease', function (): void {
    $owner = User::factory()->agents()->create();
    $other = User::factory()->agents()->create();
    Sanctum::actingAs($other);
    $lease = makeOwnedLease($owner);

    $payment = RentPayment::factory()->create([
        'lease_contract_id' => $lease->id,
        'recorded_by_user_id' => $owner->id,
    ]);

    $this->deleteJson("/api/v1/my/rent-payments/{$payment->id}")->assertForbidden();
    $this->assertDatabaseHas('rent_payments', ['id' => $payment->id]);
});
