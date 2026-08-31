<?php

use App\Models\Ad;
use App\Models\PropertyAttribute;
use App\Models\PropertyAttributeCategory;
use App\Models\User;
use App\Support\PropertyAttributeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('imports grouped property attributes with command', function (): void {
    $categoryCount = count(PropertyAttributeCatalog::categories());
    $attributeCount = collect(PropertyAttributeCatalog::categories())
        ->sum(fn (array $category): int => count($category['attributes']));

    $this->artisan('catalog:sync-attributes')
        ->assertSuccessful();

    expect(PropertyAttributeCategory::query()->count())->toBe($categoryCount);
    expect(PropertyAttribute::query()->count())->toBe($attributeCount);
});

it('uses only professional MUI icon names in the catalog', function (): void {
    // Noms qui n'existent pas dans @mui/icons-material : le front les rend en
    // fallback générique (CheckCircleOutline) au lieu de l'icône attendue.
    $invalidMuiIcons = ['Skillet', 'Kettle', 'Wardrobe'];

    $icons = collect(PropertyAttributeCatalog::categories())
        ->flatMap(fn (array $category): array => array_merge(
            [$category['icon']],
            array_column($category['attributes'], 'icon'),
        ));

    expect($icons)->not->toBeEmpty();

    $icons->each(function (string $icon) use ($invalidMuiIcons): void {
        expect($icon)->toMatch('/^[A-Z][A-Za-z0-9]+$/');
        expect($invalidMuiIcons)->not->toContain($icon);
    });

    $plaques = collect(PropertyAttributeCatalog::categories())
        ->flatMap(fn (array $category): array => $category['attributes'])
        ->firstWhere('name', 'Plaques de cuisson');

    expect($plaques['icon'])->toBe('Whatshot');
});

it('returns grouped attributes api payload', function (): void {
    $this->artisan('catalog:sync-attributes')
        ->assertSuccessful();

    $response = $this->getJson('/api/v1/property-attributes');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data',
            'grouped' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'attributes' => [
                        '*' => ['value', 'label', 'icon', 'admin_icon'],
                    ],
                ],
            ],
        ]);
});

it('validates attributes against active catalog values', function (): void {
    $this->artisan('catalog:sync-attributes')
        ->assertSuccessful();

    $owner = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    Sanctum::actingAs($owner, ['*']);

    $invalidResponse = $this->putJson("/api/v1/ads/{$ad->id}", [
        'attributes' => ['not-existing-attribute'],
    ]);

    $invalidResponse->assertUnprocessable()
        ->assertJsonValidationErrors(['attributes.0']);

    $validSlug = PropertyAttribute::query()->active()->value('slug');
    expect($validSlug)->not->toBeNull();

    $validResponse = $this->putJson("/api/v1/ads/{$ad->id}", [
        'attributes' => [$validSlug],
    ]);

    $validResponse->assertOk();
    $ad->refresh();
    expect($ad->attributes)->toContain($validSlug);
});
