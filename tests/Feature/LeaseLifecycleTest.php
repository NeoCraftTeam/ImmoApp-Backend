<?php

declare(strict_types=1);

use App\Enums\LeaseAuditEvent;
use App\Enums\LeaseStatus;
use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\LeaseSignatureAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

afterEach(function (): void {
    Carbon::setTestNow(null);
});

function makeOwnerWithLease(array $overrides = []): array
{
    $owner = User::factory()->agents()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    $lease = LeaseContract::factory()->create(array_merge([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
        'lease_start' => '2026-01-01',
        'lease_end' => '2026-12-31',
        'lease_duration_months' => 12,
        'monthly_rent' => 150000,
        'status' => LeaseStatus::Active,
    ], $overrides));

    return [$owner, $lease];
}

// ── Renew ────────────────────────────────────────────────────────

it('extends lease_end and bumps duration on renew', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01'));
    [$owner, $lease] = makeOwnerWithLease();
    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/renew", [
        'extend_months' => 6,
    ])->assertOk();

    expect($response->json('data.lease_end'))->toBe('2027-06-30')
        ->and($response->json('data.lease_duration_months'))->toBe(18)
        ->and($response->json('data.status'))->toBe(LeaseStatus::Active->value);
});

it('renew anchors off today when the lease has already expired', function (): void {
    Carbon::setTestNow(Carbon::parse('2027-02-01'));
    [$owner, $lease] = makeOwnerWithLease([
        'lease_end' => '2026-12-31',
        'status' => LeaseStatus::Expired,
    ]);
    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/renew", [
        'extend_months' => 12,
    ])->assertOk();

    expect($response->json('data.lease_end'))->toBe('2028-02-01')
        ->and($response->json('data.status'))->toBe(LeaseStatus::Active->value);
});

it('renew can also update monthly_rent', function (): void {
    [$owner, $lease] = makeOwnerWithLease();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/renew", [
        'extend_months' => 12,
        'monthly_rent' => 180000,
    ])->assertOk();

    expect((float) $lease->fresh()->monthly_rent)->toEqual(180000);
});

it('renew refuses to operate on terminated / archived leases', function (): void {
    [$owner, $lease] = makeOwnerWithLease(['status' => LeaseStatus::Terminated]);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/renew", [
        'extend_months' => 6,
    ])->assertStatus(409);
});

it('renew forbids non-owner', function (): void {
    [, $lease] = makeOwnerWithLease();
    Sanctum::actingAs(User::factory()->agents()->create());

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/renew", [
        'extend_months' => 6,
    ])->assertForbidden();
});

it('renew records an audit log entry', function (): void {
    [$owner, $lease] = makeOwnerWithLease();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/renew", [
        'extend_months' => 6,
    ])->assertOk();

    expect(
        LeaseSignatureAuditLog::where('lease_contract_id', $lease->id)
            ->where('event', LeaseAuditEvent::Renewed->value)
            ->exists()
    )->toBeTrue();
});

// ── Terminate ────────────────────────────────────────────────────

it('terminates an active lease and stamps reason + timestamp', function (): void {
    [$owner, $lease] = makeOwnerWithLease();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/terminate", [
        'reason' => 'Locataire est parti après préavis',
    ])->assertOk();

    $fresh = $lease->fresh();
    expect($fresh->status)->toBe(LeaseStatus::Terminated)
        ->and($fresh->terminated_at)->not->toBeNull()
        ->and($fresh->termination_reason)->toBe('Locataire est parti après préavis');
});

it('terminate requires a non-trivial reason', function (): void {
    [$owner, $lease] = makeOwnerWithLease();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/terminate", [
        'reason' => 'no',
    ])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
});

it('terminate refuses to operate on already-terminated leases', function (): void {
    [$owner, $lease] = makeOwnerWithLease(['status' => LeaseStatus::Terminated]);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/terminate", [
        'reason' => 'A second termination attempt',
    ])->assertStatus(409);
});

it('terminate forbids non-owner', function (): void {
    [, $lease] = makeOwnerWithLease();
    Sanctum::actingAs(User::factory()->agents()->create());

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/terminate", [
        'reason' => 'Random outsider',
    ])->assertForbidden();
});

// ── Archive ──────────────────────────────────────────────────────

it('archives an expired lease', function (): void {
    [$owner, $lease] = makeOwnerWithLease(['status' => LeaseStatus::Expired]);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/archive")->assertOk();

    $fresh = $lease->fresh();
    expect($fresh->status)->toBe(LeaseStatus::Archived)
        ->and($fresh->archived_at)->not->toBeNull();
});

it('archive refuses active leases', function (): void {
    [$owner, $lease] = makeOwnerWithLease();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/archive")->assertStatus(409);
});

it('archive forbids non-owner', function (): void {
    [, $lease] = makeOwnerWithLease(['status' => LeaseStatus::Expired]);
    Sanctum::actingAs(User::factory()->agents()->create());

    $this->postJson("/api/v1/my/lease-contracts/{$lease->id}/archive")->assertForbidden();
});

// ── Scheduled expiry sweep ───────────────────────────────────────

it('expires active leases past their lease_end via the sweep command', function (): void {
    Carbon::setTestNow(Carbon::parse('2027-01-15'));
    [, $lease] = makeOwnerWithLease([
        'lease_end' => '2026-12-31',
        'status' => LeaseStatus::Active,
    ]);

    $this->artisan('app:expire-overdue-leases')->assertSuccessful();

    expect($lease->fresh()->status)->toBe(LeaseStatus::Expired);
});

it('sweep leaves non-active leases alone', function (): void {
    Carbon::setTestNow(Carbon::parse('2027-01-15'));
    [, $terminated] = makeOwnerWithLease([
        'lease_end' => '2026-12-31',
        'status' => LeaseStatus::Terminated,
    ]);

    $this->artisan('app:expire-overdue-leases')->assertSuccessful();

    expect($terminated->fresh()->status)->toBe(LeaseStatus::Terminated);
});

it('sweep leaves in-window active leases alone', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01'));
    [, $lease] = makeOwnerWithLease([
        'lease_end' => '2026-12-31',
    ]);

    $this->artisan('app:expire-overdue-leases')->assertSuccessful();

    expect($lease->fresh()->status)->toBe(LeaseStatus::Active);
});

it('dashboard active_leases_count uses status flag, not lease_end', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-01'));
    [$owner, $active] = makeOwnerWithLease();

    // Active but terminated — should NOT count
    LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $active->ad_id,
        'status' => LeaseStatus::Terminated,
        'lease_start' => '2026-01-01',
        'lease_end' => '2026-12-31',
    ]);

    // Archived — should NOT count
    LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $active->ad_id,
        'status' => LeaseStatus::Archived,
        'archived_at' => now(),
        'lease_start' => '2026-01-01',
        'lease_end' => '2026-12-31',
    ]);

    Sanctum::actingAs($owner);
    $response = $this->getJson('/api/v1/my/stats')->assertOk();

    expect($response->json('data.active_leases_count'))->toBe(1);
});
