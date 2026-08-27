<?php

declare(strict_types=1);

use App\Enums\LeaseAuditEvent;
use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\LeaseSignatureAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

it('records an updated event when owner edits an active lease', function (): void {
    // Isolate the PDF regeneration side-effect onto a fake disk; this test
    // asserts the audit trail, not the rendered file.
    Storage::fake(config('filesystems.app_media_disk'));

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

    $this->putJson("/api/v1/my/lease-contracts/{$lease->id}", [
        'tenant_name' => 'Awa Ndiaye',
        'special_conditions' => 'Loyer payable le 5 de chaque mois.',
    ])->assertOk();

    $log = LeaseSignatureAuditLog::where('lease_contract_id', $lease->id)
        ->where('event', LeaseAuditEvent::Updated->value)
        ->first();

    expect($lease->fresh()->tenant_name)->toBe('Awa Ndiaye')
        ->and($log)->not->toBeNull()
        ->and($log->metadata['updated_fields'])->toContain('tenant_name', 'special_conditions');
});

it('rejects editing a terminated lease and records no update event', function (): void {
    $owner = User::factory()->agents()->create();
    Sanctum::actingAs($owner);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    $lease = LeaseContract::factory()->terminated()->create([
        'user_id' => $owner->id,
        'ad_id' => $ad->id,
        'tenant_name' => 'Nom Initial',
    ]);

    $this->putJson("/api/v1/my/lease-contracts/{$lease->id}", [
        'tenant_name' => 'Nom Modifié',
    ])->assertConflict();

    expect($lease->fresh()->tenant_name)->toBe('Nom Initial')
        ->and(
            LeaseSignatureAuditLog::where('lease_contract_id', $lease->id)
                ->where('event', LeaseAuditEvent::Updated->value)
                ->exists()
        )->toBeFalse();
});
