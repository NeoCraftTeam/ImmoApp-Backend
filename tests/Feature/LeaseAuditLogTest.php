<?php

declare(strict_types=1);

use App\Enums\LeaseAuditEvent;
use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\LeaseSignatureAuditLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('records a viewed event when owner fetches a lease contract', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    $lease = LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
    ]);

    $this->getJson("/api/v1/my/lease-contracts/{$lease->id}")->assertOk();

    expect(
        LeaseSignatureAuditLog::where('lease_contract_id', $lease->id)
            ->where('event', LeaseAuditEvent::Viewed->value)
            ->exists()
    )->toBeTrue();
});

it('forbids non-owner from viewing audit log', function (): void {
    $owner = User::factory()->agents()->create();
    $other = User::factory()->agents()->create();
    Sanctum::actingAs($other);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    $lease = LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
    ]);

    $this->getJson("/api/v1/my/lease-contracts/{$lease->id}/audit-log")->assertForbidden();
});

it('returns audit log entries in chronological order', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    $lease = LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
    ]);

    LeaseSignatureAuditLog::record(
        leaseContractId: $lease->id,
        event: LeaseAuditEvent::Generated,
        userId: $owner->id,
        metadata: ['contract_number' => $lease->contract_number],
    );

    LeaseSignatureAuditLog::record(
        leaseContractId: $lease->id,
        event: LeaseAuditEvent::Viewed,
        userId: $owner->id,
    );

    $data = $this->getJson("/api/v1/my/lease-contracts/{$lease->id}/audit-log")
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['event'])->toBe(LeaseAuditEvent::Generated->value)
        ->and($data[1]['event'])->toBe(LeaseAuditEvent::Viewed->value);
});

it('audit log entry includes user relation', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    $lease = LeaseContract::factory()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
    ]);

    LeaseSignatureAuditLog::record(
        leaseContractId: $lease->id,
        event: LeaseAuditEvent::Downloaded,
        userId: $owner->id,
    );

    $data = $this->getJson("/api/v1/my/lease-contracts/{$lease->id}/audit-log")
        ->assertOk()
        ->json('data');

    expect($data[0]['user'])->not->toBeNull()
        ->and($data[0]['user']['id'])->toBe($owner->id);
});
