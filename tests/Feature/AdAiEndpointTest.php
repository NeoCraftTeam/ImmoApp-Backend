<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AiDescriptionEnhancer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeOwner(): User
{
    return User::factory()->agents()->create([
        'is_active'          => true,
        'email_verified_at'  => now(),
    ]);
}

function fakeEnhancer(string $returnValue): void
{
    $mock = Mockery::mock(AiDescriptionEnhancer::class);
    $mock->shouldReceive('enhance')->andReturn($returnValue)->byDefault();
    $mock->shouldReceive('enhanceTitle')->andReturn($returnValue)->byDefault();
    $mock->shouldReceive('generateFromAttributes')->andReturn($returnValue)->byDefault();
    $mock->shouldReceive('summarizeLeaseContract')->andReturn($returnValue)->byDefault();
    app()->instance(AiDescriptionEnhancer::class, $mock);
}

// ─── enhance-description ────────────────────────────────────────────────────

it('returns 401 on enhance-description without auth', function (): void {
    $this->postJson('/api/v1/ads/ai/enhance-description', ['description' => 'test'])
        ->assertUnauthorized();
});

it('returns 422 when description is missing on enhance-description', function (): void {
    $owner = makeOwner();
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/enhance-description', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});

it('enhance-description returns enhanced text', function (): void {
    $owner = makeOwner();
    fakeEnhancer('Description améliorée par l\'IA.');

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/enhance-description', [
            'description' => 'Studio 2 pièces bien situé.',
        ])
        ->assertOk()
        ->assertJsonPath('enhanced', 'Description améliorée par l\'IA.');
});

// ─── enhance-title ───────────────────────────────────────────────────────────

it('returns 401 on enhance-title without auth', function (): void {
    $this->postJson('/api/v1/ads/ai/enhance-title', ['title' => 'test'])
        ->assertUnauthorized();
});

it('returns 422 when title is missing on enhance-title', function (): void {
    $owner = makeOwner();
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/enhance-title', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('enhance-title returns enhanced title', function (): void {
    $owner = makeOwner();
    fakeEnhancer('Appartement F3 meublé – Bastos, Yaoundé');

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/enhance-title', [
            'title'            => 'Appart meublé bastos',
            'type'             => 'Appartement',
            'city'             => 'Yaoundé',
            'transaction_type' => 'location',
        ])
        ->assertOk()
        ->assertJsonPath('enhanced', 'Appartement F3 meublé – Bastos, Yaoundé');
});

it('enhance-title rejects title longer than 500 chars', function (): void {
    $owner = makeOwner();
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/enhance-title', [
            'title' => str_repeat('x', 501),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

// ─── generate-from-attributes ────────────────────────────────────────────────

it('returns 401 on generate-from-attributes without auth', function (): void {
    $this->postJson('/api/v1/ads/ai/generate-from-attributes', ['type' => 'Villa'])
        ->assertUnauthorized();
});

it('generate-from-attributes returns generated description', function (): void {
    $owner = makeOwner();
    fakeEnhancer('Villa moderne à Douala, quartier Bonamoussadi, 4 chambres.');

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/generate-from-attributes', [
            'type'             => 'Villa',
            'city'             => 'Douala',
            'quarter'          => 'Bonamoussadi',
            'bedrooms'         => 4,
            'surface'          => 200,
            'price'            => 500000,
            'transaction_type' => 'location',
        ])
        ->assertOk()
        ->assertJsonPath('generated', 'Villa moderne à Douala, quartier Bonamoussadi, 4 chambres.');
});

it('generate-from-attributes accepts empty body and returns generated string', function (): void {
    $owner = makeOwner();
    fakeEnhancer('');

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/generate-from-attributes', [])
        ->assertOk()
        ->assertJsonStructure(['generated']);
});

it('generate-from-attributes rejects invalid bedrooms value', function (): void {
    $owner = makeOwner();
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/generate-from-attributes', [
            'bedrooms' => 999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bedrooms']);
});
