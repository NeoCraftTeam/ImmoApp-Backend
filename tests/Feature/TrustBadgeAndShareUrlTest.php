<?php

declare(strict_types=1);

use App\Enums\TrustScoreTier;
use App\Models\Ad;
use App\Models\TrustScore;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ─────────────────────────────────────────────────────────────────────────────
// Item 6 — Trust badge in UserResource
// ─────────────────────────────────────────────────────────────────────────────

it('UserResource exposes is_verified true when email is verified', function (): void {
    $user = User::factory()->agents()->create([
        'email_verified_at' => now(),
    ]);
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.is_verified', true);
});

it('UserResource is_verified reflects email_verified_at null correctly via resource', function (): void {
    // EnsureEmailIsVerified middleware blocks API access for unverified users,
    // so we verify the resource logic via direct instantiation.
    $user = User::factory()->agents()->make(['email_verified_at' => null]);

    $resource = new \App\Http\Resources\UserResource($user);
    $data = $resource->resolve(new \Illuminate\Http\Request());

    expect($data['is_verified'])->toBeFalse();
});

it('UserResource defaults to non_verifie tier when no trust score exists', function (): void {
    $user = User::factory()->agents()->create();
    Sanctum::actingAs($user);

    $data = $this->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->json('data');

    expect($data['trust_tier'])->toBe('non_verifie')
        ->and($data['trust_score'])->toBe(0)
        ->and($data['trust_tier_label'])->toBe('Non vérifié');
});

it('UserResource exposes computed trust tier when score exists', function (): void {
    $user = User::factory()->agents()->create();

    TrustScore::create([
        'user_id' => $user->id,
        'role_context' => 'agent',
        'score' => 65,
        'tier' => TrustScoreTier::Or,
        'components' => [],
        'computed_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $data = $this->getJson("/api/v1/users/{$user->id}")
        ->assertOk()
        ->json('data');

    expect($data['trust_tier'])->toBe('or')
        ->and($data['trust_score'])->toBe(65)
        ->and($data['trust_tier_label'])->toBe('Or');
});

// ─────────────────────────────────────────────────────────────────────────────
// Item 6 — Trust badge in AdResource (inline owner)
// ─────────────────────────────────────────────────────────────────────────────

it('AdResource owner section exposes is_verified and trust_tier', function (): void {
    $owner = User::factory()->agents()->create([
        'email_verified_at' => now(),
    ]);

    TrustScore::create([
        'user_id' => $owner->id,
        'role_context' => 'agent',
        'score' => 45,
        'tier' => TrustScoreTier::Argent,
        'components' => [],
        'computed_at' => now(),
    ]);

    $viewer = User::factory()->create();
    Sanctum::actingAs($viewer);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    $data = $this->getJson("/api/v1/ads/{$ad->id}?include=user")
        ->assertOk()
        ->json('data');

    expect($data['user']['is_verified'])->toBeTrue()
        ->and($data['user']['trust_tier'])->toBe('argent')
        ->and($data['user']['trust_score'])->toBe(45);
});

// ─────────────────────────────────────────────────────────────────────────────
// Item 7 — WhatsApp share URL in AdResource
// ─────────────────────────────────────────────────────────────────────────────

it('AdResource exposes whatsapp_share_url containing wa.me', function (): void {
    $owner = User::factory()->agents()->create();
    $viewer = User::factory()->create();
    Sanctum::actingAs($viewer);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create([
            'user_id' => $owner->id,
            'status' => 'available',
            'title' => 'Bel appartement à Douala',
        ]);
    });

    $data = $this->getJson("/api/v1/ads/{$ad->id}")
        ->assertOk()
        ->json('data');

    expect($data['whatsapp_share_url'])
        ->toStartWith('https://wa.me/?text=')
        ->and(urldecode($data['whatsapp_share_url']))
        ->toContain('KeyHome')
        ->toContain('Bel appartement');
});

it('AdResource exposes canonical_url pointing to frontend ads path', function (): void {
    $owner = User::factory()->agents()->create();
    $viewer = User::factory()->create();
    Sanctum::actingAs($viewer);

    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id, 'status' => 'available']);
    });

    $data = $this->getJson("/api/v1/ads/{$ad->id}")
        ->assertOk()
        ->json('data');

    expect($data['canonical_url'])
        ->toContain('/ads/')
        ->not->toBeEmpty();
});
