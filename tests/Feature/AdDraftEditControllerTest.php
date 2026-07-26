<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\PropertyAttribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The edit-draft endpoints stash pending changes on a published ad's
 * `draft_payload` JSON column until the owner promotes them with `apply()`.
 * These tests pin the contract that:
 *   - `save()` accepts the same field shape as autosave
 *   - `apply()` re-validates the stored payload (catches stale data)
 *   - `apply()` refuses to nullify a field that's required for publish
 *   - attributes must be active, existing slugs at save time
 *   - draft endpoints reject draft-status ads (those use the standard flow)
 */
function createOwnerWithPublishedAd(array $overrides = []): Ad
{
    $owner = User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    return Ad::factory()
        ->for($owner)
        ->create(array_merge([
            'status' => AdStatus::AVAILABLE,
            'is_visible' => true,
            'attributes' => [],
        ], $overrides));
}

it('save accepts a partial edit and stores it under draft_payload', function (): void {
    $ad = createOwnerWithPublishedAd(['title' => 'Original']);

    $this->actingAs($ad->user, 'sanctum')
        ->patchJson("/api/v1/ads/{$ad->id}/edit-draft", [
            'title' => 'Modifié',
            'description' => 'Nouvelle description',
        ])
        ->assertOk()
        ->assertJsonPath('data.draft_payload.title', 'Modifié');

    $ad->refresh();
    expect($ad->title)->toBe('Original');
    expect($ad->draft_payload)->toMatchArray([
        'title' => 'Modifié',
        'description' => 'Nouvelle description',
    ]);
});

it('save rejects unknown attribute slugs', function (): void {
    $ad = createOwnerWithPublishedAd();

    $this->actingAs($ad->user, 'sanctum')
        ->patchJson("/api/v1/ads/{$ad->id}/edit-draft", [
            'attributes' => ['ce-slug-nexiste-pas'],
        ])
        ->assertUnprocessable();
});

it('save accepts active attribute slugs and stores them on the draft', function (): void {
    $ad = createOwnerWithPublishedAd();
    $attr = PropertyAttribute::factory()->create(['is_active' => true]);

    $this->actingAs($ad->user, 'sanctum')
        ->patchJson("/api/v1/ads/{$ad->id}/edit-draft", [
            'attributes' => [$attr->slug],
        ])
        ->assertOk();

    expect($ad->fresh()->draft_payload['attributes'])->toBe([$attr->slug]);
});

it('apply promotes draft_payload onto the live ad and clears the draft', function (): void {
    $ad = createOwnerWithPublishedAd(['title' => 'Avant', 'price' => 100000]);
    $ad->forceFill(['draft_payload' => ['title' => 'Après', 'price' => 150000]])->saveQuietly();

    $this->actingAs($ad->user, 'sanctum')
        ->postJson("/api/v1/ads/{$ad->id}/edit-draft/apply")
        ->assertOk();

    $ad->refresh();
    expect($ad->title)->toBe('Après');
    expect((int) $ad->price)->toBe(150000);
    expect($ad->draft_payload)->toBeNull();
});

it('apply refuses to nullify a publish-required field via empty string', function (): void {
    $ad = createOwnerWithPublishedAd(['title' => 'Bonne annonce']);

    // The owner stashed an empty-string title in draft_payload. `apply()`
    // must reject this because publishing a blank title is never allowed.
    $ad->forceFill(['draft_payload' => ['title' => '']])->saveQuietly();

    $this->actingAs($ad->user, 'sanctum')
        ->postJson("/api/v1/ads/{$ad->id}/edit-draft/apply")
        ->assertStatus(422)
        ->assertJsonPath('errors.missing_fields.0', 'title');

    // Live ad untouched, draft preserved so the owner can edit it.
    $ad->refresh();
    expect($ad->title)->toBe('Bonne annonce');
    expect($ad->draft_payload)->toBe(['title' => '']);
});

it('discard clears draft_payload without touching the live ad', function (): void {
    $ad = createOwnerWithPublishedAd(['title' => 'Live']);
    $ad->forceFill(['draft_payload' => ['title' => 'Brouillon']])->saveQuietly();

    $this->actingAs($ad->user, 'sanctum')
        ->deleteJson("/api/v1/ads/{$ad->id}/edit-draft")
        ->assertOk();

    $ad->refresh();
    expect($ad->title)->toBe('Live');
    expect($ad->draft_payload)->toBeNull();
});

it('rejects edit-draft endpoints when the ad is itself a DRAFT', function (): void {
    $owner = User::factory()->agents()->create(['is_active' => true]);
    $draft = Ad::factory()->for($owner)->create(['status' => AdStatus::DRAFT]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/ads/{$draft->id}/edit-draft", ['title' => 'X'])
        ->assertStatus(422);
});
