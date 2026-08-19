<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\OwnerAdPrivateNote;
use App\Models\User;

it('lets only the posting owner store and read the encrypted private property-owner note', function (): void {
    $owner = User::factory()->agents()->create();
    $other = User::factory()->agents()->create();
    $ad = Ad::withoutSyncingToSearch(fn () => Ad::factory()->create(['user_id' => $owner->id]));

    $payload = [
        'is_property_owner' => false,
        'owner_name' => 'Propriétaire réel',
        'owner_address' => 'Adresse confidentielle',
        'owner_phone' => '+237 699 000 000',
        'owner_email' => 'proprietaire@example.com',
        'notes' => 'Appeler avant toute visite.',
    ];

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/my/ads/{$ad->id}/private-owner-note", $payload)
        ->assertOk()
        ->assertJsonPath('data.owner_name', 'Propriétaire réel');

    $row = OwnerAdPrivateNote::query()->firstOrFail();
    expect($row->getRawOriginal('owner_name'))->not->toContain('Propriétaire réel')
        ->and($row->owner_name)->toBe('Propriétaire réel');

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/my/ads/{$ad->id}/private-owner-note")
        ->assertOk()
        ->assertJsonPath('data.owner_phone', '+237 699 000 000');

    $this->actingAs($other, 'sanctum')
        ->getJson("/api/v1/my/ads/{$ad->id}/private-owner-note")
        ->assertNotFound();
});

it('never exposes the private note through the public ad resource', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = Ad::withoutSyncingToSearch(fn () => Ad::factory()->create([
        'user_id' => $owner->id,
        'status' => 'available',
    ]));
    OwnerAdPrivateNote::query()->create([
        'ad_id' => $ad->id,
        'user_id' => $owner->id,
        'is_property_owner' => false,
        'owner_name' => 'Secret',
    ]);

    $this->getJson("/api/v1/ads/{$ad->id}")
        ->assertOk()
        ->assertJsonMissing(['owner_name' => 'Secret'])
        ->assertJsonMissingPath('data.private_owner_note');
});

it('requires the real owner name when the poster is an intermediary', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = Ad::withoutSyncingToSearch(fn () => Ad::factory()->create(['user_id' => $owner->id]));

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/my/ads/{$ad->id}/private-owner-note", [
            'is_property_owner' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('owner_name');
});
