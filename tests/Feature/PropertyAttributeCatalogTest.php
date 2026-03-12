<?php

use App\Models\Ad;
use App\Models\PropertyAttribute;
use App\Models\User;
use App\Support\PropertyAttributeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('imports grouped property attributes with command', function (): void {
    $categoryCount = count(PropertyAttributeCatalog::categories());
    $attributeCount = collect(PropertyAttributeCatalog::categories())
        ->sum(fn (array $category): int => count($category['attributes']));

    $this->artisan('make:upload-attributes')
        ->assertSuccessful();

    expect(\App\Models\PropertyAttributeCategory::query()->count())->toBe($categoryCount);
    expect(PropertyAttribute::query()->count())->toBe($attributeCount);
});

it('returns grouped attributes api payload', function (): void {
    $this->artisan('make:upload-attributes')
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
    $this->artisan('make:upload-attributes')
        ->assertSuccessful();

    $owner = User::factory()->create(['role' => 'agent', 'type' => 'individual']);
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    Sanctum::actingAs($owner);

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
