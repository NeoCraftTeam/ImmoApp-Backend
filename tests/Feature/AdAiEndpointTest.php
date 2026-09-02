<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Ai\AiDescriptionEnhancer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeOwner(): User
{
    return User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
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

it('enhance-description forwards form context and attributes to the enhancer', function (): void {
    $owner = makeOwner();

    $mock = Mockery::mock(AiDescriptionEnhancer::class);
    $mock->shouldReceive('enhance')
        ->once()
        ->with('Terrain titré à Limbé.', Mockery::on(fn (array $context): bool => ($context['type'] ?? null) === 'Terrain'
            && ($context['city'] ?? null) === 'Limbé'
            && ($context['surface'] ?? null) === 100
            && ($context['features'] ?? null) === ['Titre foncier', 'Bordure de route']))
        ->andReturn('Description enrichie.');
    app()->instance(AiDescriptionEnhancer::class, $mock);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/enhance-description', [
            'description' => 'Terrain titré à Limbé.',
            'type' => 'Terrain',
            'city' => 'Limbé',
            'surface' => 100,
            'attributes' => ['Titre foncier', 'Bordure de route'],
        ])
        ->assertOk()
        ->assertJsonPath('enhanced', 'Description enrichie.');
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
            'title' => 'Appart meublé bastos',
            'type' => 'Appartement',
            'city' => 'Yaoundé',
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
            'type' => 'Villa',
            'city' => 'Douala',
            'quarter' => 'Bonamoussadi',
            'bedrooms' => 4,
            'surface' => 200,
            'price' => 500000,
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

// ─── stream-enhance (SSE) ─────────────────────────────────────────────────────

it('returns 401 on stream-enhance without auth', function (): void {
    $this->postJson('/api/v1/ads/ai/stream-enhance', ['description' => 'test'])
        ->assertUnauthorized();
});

it('stream-enhance emits the enhanced deltas and a done event', function (): void {
    $owner = makeOwner();

    $mock = Mockery::mock(AiDescriptionEnhancer::class);
    $mock->shouldReceive('streamEnhance')
        ->once()
        ->andReturnUsing(function (string $description, callable $onChunk): void {
            $onChunk('Terrain titré à Bastos.');
            $onChunk(' Idéal pour construire.');
        });
    app()->instance(AiDescriptionEnhancer::class, $mock);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/stream-enhance', [
            'description' => 'terrain bastos',
        ]);

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('data: '.json_encode(['delta' => 'Terrain titré à Bastos.']))
        ->toContain('event: done');
});

it('stream-enhance degrades to a clean error event when the enhancer throws mid-stream', function (): void {
    $owner = makeOwner();

    $mock = Mockery::mock(AiDescriptionEnhancer::class);
    $mock->shouldReceive('streamEnhance')
        ->once()
        ->andReturnUsing(function (string $description, callable $onChunk): void {
            $onChunk('Terrain titré à Bastos.');

            throw new RuntimeException('provider exploded mid-stream');
        });
    app()->instance(AiDescriptionEnhancer::class, $mock);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/ads/ai/stream-enhance', [
            'description' => 'terrain bastos',
        ]);

    // The 200 + headers are already flushed, so the status can't change — but the
    // raw internal-error JSON must never leak into the open SSE body.
    $response->assertOk();

    $content = $response->streamedContent();
    expect($content)
        ->toContain('event: error')
        ->toContain('event: done')
        ->not->toContain('SERVER_ERROR')
        ->not->toContain('Une erreur interne est survenue');
});
