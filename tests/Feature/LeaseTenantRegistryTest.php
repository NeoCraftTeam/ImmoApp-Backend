<?php

declare(strict_types=1);

/**
 * P2 : génération de bail ↔ registre "mes locataires".
 *
 * Vérifie que générer un contrat de bail alimente le registre des locataires
 * du bailleur (auto-enregistrement par téléphone dans son scope) et pose le
 * lien `lease_contracts.tenant_id`, avec la possibilité de lier explicitement
 * un locataire déjà enregistré.
 */

use App\Models\Ad;
use App\Models\LeaseContract;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The generation path renders + stores a PDF; isolate it onto a fake disk.
    Storage::fake(config('filesystems.app_media_disk'));
});

/**
 * @return array{0: User, 1: Ad}
 */
function ownerWithAd(): array
{
    $owner = User::factory()->agents()->create();

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    return [$owner, $ad];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function leasePayload(array $overrides = []): array
{
    return array_merge([
        'tenant_name' => 'Awa Ndiaye',
        'tenant_phone' => '+237690000001',
        'lease_start' => now()->addDay()->toDateString(),
        'lease_duration_months' => 12,
    ], $overrides);
}

it('auto-registers the tenant and links the lease when no tenant_id is given', function (): void {
    [$owner, $ad] = ownerWithAd();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/my/lease-contracts/{$ad->id}/generate", leasePayload())
        ->assertCreated();

    $tenant = Tenant::query()
        ->where('user_id', $owner->id)
        ->where('phone', '+237690000001')
        ->first();

    $lease = LeaseContract::query()->where('user_id', $owner->id)->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Awa Ndiaye')
        ->and($lease->tenant_id)->toBe($tenant->id);

    // The "mes locataires" registry now reflects the generated lease.
    $data = $this->getJson('/api/v1/my/tenants')->assertOk()->json('data');
    $entry = collect($data)->firstWhere('id', $tenant->id);
    expect($entry['lease_contracts_count'])->toBe(1);
});

it('reuses an existing tenant matched on phone instead of duplicating it', function (): void {
    [$owner, $ad] = ownerWithAd();
    Sanctum::actingAs($owner);

    $existing = Tenant::query()->create([
        'user_id' => $owner->id,
        'name' => 'Nom Existant',
        'phone' => '+237690000003',
    ]);

    $this->postJson(
        "/api/v1/my/lease-contracts/{$ad->id}/generate",
        leasePayload(['tenant_name' => 'Nom Différent', 'tenant_phone' => '+237690000003'])
    )->assertCreated();

    $lease = LeaseContract::query()->where('user_id', $owner->id)->first();

    expect(Tenant::query()->where('user_id', $owner->id)->count())->toBe(1)
        ->and($lease->tenant_id)->toBe($existing->id)
        // firstOrCreate must not overwrite the registry name on match.
        ->and($existing->fresh()->name)->toBe('Nom Existant');
});

it('honours an explicit owner-owned tenant_id over the free-text fields', function (): void {
    [$owner, $ad] = ownerWithAd();
    Sanctum::actingAs($owner);

    $tenant = Tenant::query()->create([
        'user_id' => $owner->id,
        'name' => 'Locataire Choisi',
        'phone' => '+237690000010',
    ]);

    $this->postJson(
        "/api/v1/my/lease-contracts/{$ad->id}/generate",
        // Different phone in the body must NOT spawn a second tenant.
        leasePayload(['tenant_id' => $tenant->id, 'tenant_phone' => '+237690000099'])
    )->assertCreated();

    $lease = LeaseContract::query()->where('user_id', $owner->id)->first();

    expect($lease->tenant_id)->toBe($tenant->id)
        ->and(Tenant::query()->where('user_id', $owner->id)->count())->toBe(1);
});

it('rejects a tenant_id belonging to another owner', function (): void {
    [$owner, $ad] = ownerWithAd();
    $other = User::factory()->agents()->create();

    $foreignTenant = Tenant::query()->create([
        'user_id' => $other->id,
        'name' => 'Locataire Étranger',
        'phone' => '+237690000020',
    ]);

    Sanctum::actingAs($owner);

    $this->postJson(
        "/api/v1/my/lease-contracts/{$ad->id}/generate",
        leasePayload(['tenant_id' => $foreignTenant->id])
    )->assertJsonValidationErrors('tenant_id');

    expect(LeaseContract::query()->where('user_id', $owner->id)->exists())->toBeFalse();
});

it('scopes auto-registration per owner — no cross-owner tenant reuse', function (): void {
    [$owner, $ad] = ownerWithAd();
    $other = User::factory()->agents()->create();

    // Another owner already has a tenant with the same phone.
    Tenant::query()->create([
        'user_id' => $other->id,
        'name' => 'Chez Autre Bailleur',
        'phone' => '+237690000030',
    ]);

    Sanctum::actingAs($owner);

    $this->postJson(
        "/api/v1/my/lease-contracts/{$ad->id}/generate",
        leasePayload(['tenant_phone' => '+237690000030'])
    )->assertCreated();

    // A distinct tenant is registered under the acting owner.
    $mine = Tenant::query()->where('user_id', $owner->id)->where('phone', '+237690000030')->first();
    $lease = LeaseContract::query()->where('user_id', $owner->id)->first();

    expect($mine)->not->toBeNull()
        ->and($lease->tenant_id)->toBe($mine->id)
        ->and(Tenant::query()->where('phone', '+237690000030')->count())->toBe(2);
});
