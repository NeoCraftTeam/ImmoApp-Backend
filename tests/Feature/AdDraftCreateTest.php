<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('creates a draft ad with only a title and nullable address fields', function (): void {
    $agent = User::factory()->create([
        'role' => 'agent',
        'type' => 'individual',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($agent, ['*']);

    $response = $this->postJson('/api/v1/ads', [
        'title' => 'Brouillon sans adresse',
        'is_draft' => true,
        'adresse' => null,
        'description' => null,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.ad.title', 'Brouillon sans adresse')
        ->assertJsonPath('data.ad.status', AdStatus::DRAFT->value)
        ->assertJsonPath('data.ad.adresse', '');

    $ad = Ad::query()->first();
    expect($ad)->not->toBeNull();
    expect($ad?->status)->toBe(AdStatus::DRAFT);
    expect($ad?->adresse)->toBeNull();
    expect($ad?->description)->toBeNull();
    expect($ad?->quarter_id)->toBeNull();
    expect($ad?->type_id)->toBeNull();
});

it('rejects draft creation without a title', function (): void {
    $agent = User::factory()->create([
        'role' => 'agent',
        'type' => 'individual',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($agent, ['*']);

    $this->postJson('/api/v1/ads', [
        'is_draft' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});
