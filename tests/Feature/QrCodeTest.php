<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use App\Services\AdUrlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function expectedFrontendBase(): string
{
    return rtrim((string) config('app.frontend_url', config('app.url')), '/');
}

it('returns qr meta json for the ad owner', function (): void {
    $owner = User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $ad = Ad::factory()->for($owner)->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/my/ads/{$ad->id}/qr-code")
        ->assertOk();

    $slug = $ad->slug ?: $ad->id;
    $adUrl = $response->json('data.ad_url');
    $prof = $response->json('data.profile_url');
    expect($adUrl)->toStartWith(expectedFrontendBase().'/ads/'.$slug.'?');
    expect($adUrl)->toContain('utm_source=keyhome')
        ->and($adUrl)->toContain('utm_medium=qr')
        ->and($adUrl)->toContain('utm_campaign=owner_share')
        ->and($adUrl)->toContain('utm_content=ad_'.$ad->id);

    $username = $owner->username ?: $owner->id;
    expect($prof)->toStartWith(expectedFrontendBase().'/bailleurs/'.$username.'?')
        ->and($prof)->toContain('utm_source=keyhome')
        ->and($prof)->toContain('utm_medium=qr')
        ->and($prof)->toContain('utm_campaign=owner_share')
        ->and($prof)->toContain('utm_content=profile_'.$owner->id);

    expect($response->json('data.qr_data_uri'))->toStartWith('data:image/png;base64,');
});

it('still builds utm-tracked listing urls when marketing callers opt in', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = Ad::factory()->for($owner)->create();
    $svc = app(AdUrlBuilder::class);

    $defaultClean = $svc->adListingUrl($ad);
    expect($defaultClean)->not->toContain('utm_');

    $tracked = $svc->adListingUrl($ad, 'qr', true);
    expect($tracked)->toContain('utm_source=keyhome');
    expect($tracked)->toContain('utm_medium=qr');

    $clean = $svc->adListingUrl($ad, 'qr', false);
    expect($clean)->not->toContain('utm_');
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

    $username = $owner->username ?: $owner->id;
    $profileUrl = $response->json('data.profile_url');
    expect($profileUrl)->toStartWith(expectedFrontendBase().'/bailleurs/'.$username.'?');
    expect($profileUrl)->toContain('utm_source=keyhome')
        ->and($profileUrl)->toContain('utm_medium=qr')
        ->and($profileUrl)->toContain('utm_campaign=owner_share')
        ->and($profileUrl)->toContain('utm_content=profile_'.$owner->id);
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

it('business card preview html does not expose utm_* as plaintext', function (): void {
    $owner = User::factory()->agents()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $html = $this->actingAs($owner, 'sanctum')
        ->get('/api/v1/my/profile/business-card/preview')
        ->assertOk()
        ->getContent();

    expect(is_string($html) && $html !== '')->toBeTrue();
    expect($html)->not->toContain('utm_');
});
