<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns qr meta json for the ad owner', function (): void {
    $owner = User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $ad = Ad::factory()->for($owner)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/my/ads/{$ad->id}/qr-code")
        ->assertOk();

    $adUrl = $response->json('data.ad_url');
    expect($adUrl)->toBeString()->toContain('/ads/');
    expect($adUrl)->toContain('utm_source=keyhome');
    expect($response->json('data.qr_data_uri'))->toStartWith('data:image/png;base64,');
});

it('forbids qr meta for an ad owned by someone else', function (): void {
    $owner = User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $intruder = User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $ad = Ad::factory()->for($owner)->create();

    $this->actingAs($intruder, 'sanctum')
        ->getJson("/api/v1/my/ads/{$ad->id}/qr-code")
        ->assertForbidden();
});

it('returns a png for ad qr image', function (): void {
    $owner = User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $ad = Ad::factory()->for($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->get("/api/v1/my/ads/{$ad->id}/qr-code/image")
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('downloads a placarde pdf', function (): void {
    $owner = User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $ad = Ad::factory()->for($owner)->create();

    $this->actingAs($owner, 'sanctum')
        ->get("/api/v1/my/ads/{$ad->id}/placarde")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('returns profile qr meta for an agent', function (): void {
    $owner = User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/my/profile/qr-code')
        ->assertOk();

    expect($response->json('data.profile_url'))->toContain('/bailleurs/');
    expect($response->json('data.qr_data_uri'))->toStartWith('data:image/png;base64,');
});

it('forbids profile qr for a customer', function (): void {
    $customer = User::factory()->customers()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/my/profile/qr-code')
        ->assertForbidden();
});
