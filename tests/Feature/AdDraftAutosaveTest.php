<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\PropertyAttribute;
use App\Models\Quarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createOwnerDraft(): Ad
{
    $owner = User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    return Ad::factory()
        ->for($owner)
        ->create(['status' => AdStatus::DRAFT, 'attributes' => []]);
}

it('autosave persists every supported text/number field on the draft', function (): void {
    $draft = createOwnerDraft();
    $owner = $draft->user;
    $newQuarter = Quarter::factory()->create();

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/ads/{$draft->id}/autosave", [
            'title' => 'Studio meublé centre-ville',
            'description' => 'Studio rénové, chauffage, calme et lumineux.',
            'adresse' => 'Rue des Acacias',
            'price' => 185000,
            'surface_area' => 32.5,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'has_parking' => true,
            'deposit_amount' => '2 mois de caution',
            'minimum_lease_duration' => '6 mois ferme',
            'charges_forfaitaires' => false,
            'charges_montant_forfait' => 12000,
            'charges_eau' => 4500,
            'charges_electricite' => 9800,
            'charges_autres' => 'Gardiennage : 6 000 FCFA/mois',
            'transaction_type' => 'location',
            'quarter_id' => $newQuarter->id,
        ])
        ->assertOk();

    $draft->refresh();

    expect($draft->title)->toBe('Studio meublé centre-ville');
    expect($draft->adresse)->toBe('Rue des Acacias');
    expect((int) $draft->price)->toBe(185000);
    expect((float) $draft->surface_area)->toBe(32.5);
    expect($draft->bedrooms)->toBe(1);
    expect($draft->has_parking)->toBeTrue();
    expect($draft->charges_forfaitaires)->toBeFalse();
    expect((int) $draft->charges_montant_forfait)->toBe(12000);
    expect((int) $draft->charges_eau)->toBe(4500);
    expect((int) $draft->charges_electricite)->toBe(9800);
    expect($draft->charges_autres)->toBe('Gardiennage : 6 000 FCFA/mois');
    expect($draft->quarter_id)->toBe($newQuarter->id);
});

it('autosave updates the PostGIS location only when both coordinates are supplied', function (): void {
    $draft = createOwnerDraft();

    $this->actingAs($draft->user, 'sanctum')
        ->patchJson("/api/v1/ads/{$draft->id}/autosave", [
            'latitude' => 4.0511,
            'longitude' => 9.7679,
        ])
        ->assertOk();

    $draft->refresh();

    expect($draft->location)->not->toBeNull();
    expect($draft->location?->getLatitude())->toEqualWithDelta(4.0511, 0.0001);
    expect($draft->location?->getLongitude())->toEqualWithDelta(9.7679, 0.0001);
});

it('autosave preserves the previous location when only one coordinate is supplied', function (): void {
    $draft = createOwnerDraft();
    $previous = $draft->location;

    $this->actingAs($draft->user, 'sanctum')
        ->patchJson("/api/v1/ads/{$draft->id}/autosave", [
            'latitude' => 5.5,
        ])
        ->assertOk();

    $draft->refresh();

    expect($draft->location?->getLatitude())
        ->toEqualWithDelta($previous->getLatitude(), 0.0001);
});

it('autosave persists property attributes (slug whitelist)', function (): void {
    $draft = createOwnerDraft();
    $wifi = PropertyAttribute::factory()->create([
        'slug' => 'wifi-'.uniqid(),
        'is_active' => true,
    ]);
    $parking = PropertyAttribute::factory()->create([
        'slug' => 'parking-'.uniqid(),
        'is_active' => true,
    ]);

    $this->actingAs($draft->user, 'sanctum')
        ->patchJson("/api/v1/ads/{$draft->id}/autosave", [
            'attributes' => [$wifi->slug, $parking->slug, $wifi->slug],
        ])
        ->assertOk();

    $draft->refresh();

    expect($draft->getAttribute('attributes'))
        ->toContain($wifi->slug)
        ->toContain($parking->slug);
    expect(count($draft->getAttribute('attributes')))->toBe(2);
});

it('autosave rejects attributes that do not exist or are inactive', function (): void {
    $draft = createOwnerDraft();
    $inactive = PropertyAttribute::factory()->inactive()->create([
        'slug' => 'inactive-'.uniqid(),
    ]);

    $this->actingAs($draft->user, 'sanctum')
        ->patchJson("/api/v1/ads/{$draft->id}/autosave", [
            'attributes' => ['this-slug-does-not-exist'],
        ])
        ->assertStatus(422);

    $this->actingAs($draft->user, 'sanctum')
        ->patchJson("/api/v1/ads/{$draft->id}/autosave", [
            'attributes' => [$inactive->slug],
        ])
        ->assertStatus(422);
});

it('autosave clears attributes when an empty array is sent', function (): void {
    $draft = createOwnerDraft();
    $existing = PropertyAttribute::factory()->create([
        'slug' => 'pool-'.uniqid(),
        'is_active' => true,
    ]);
    $draft->forceFill(['attributes' => [$existing->slug]])->save();

    $this->actingAs($draft->user, 'sanctum')
        ->patchJson("/api/v1/ads/{$draft->id}/autosave", [
            'attributes' => [],
        ])
        ->assertOk();

    $draft->refresh();

    expect($draft->getAttribute('attributes'))->toBe([]);
});

it('autosave refuses non-draft ads with 422', function (): void {
    $draft = createOwnerDraft();
    $draft->forceFill(['status' => AdStatus::AVAILABLE])->save();

    $this->actingAs($draft->user, 'sanctum')
        ->patchJson("/api/v1/ads/{$draft->id}/autosave", [
            'title' => 'Nope',
        ])
        ->assertStatus(422);
});

it('autosave is forbidden for users who do not own the ad', function (): void {
    $draft = createOwnerDraft();
    $stranger = User::factory()->customers()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($stranger, 'sanctum')
        ->patchJson("/api/v1/ads/{$draft->id}/autosave", [
            'title' => 'Hijack attempt',
        ])
        ->assertForbidden();
});
