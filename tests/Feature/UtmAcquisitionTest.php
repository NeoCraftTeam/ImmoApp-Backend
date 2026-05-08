<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

test('visit tracking accepts utm_content and utm_term', function (): void {
    $response = $this->postJson('/api/v1/track/visit', [
        'session_id' => 'test-session-utm-extra',
        'utm_source' => 'tiktok',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'spring_launch',
        'utm_content' => 'video_a',
        'utm_term' => 'douala+rent',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('site_visits', [
        'session_id' => 'test-session-utm-extra',
        'utm_content' => 'video_a',
        'utm_term' => 'douala+rent',
        'source' => 'paid',
    ]);
});

test('visit tracking inserts two rows when only utm_content differs', function (): void {
    $base = [
        'session_id' => 'sess-content-split',
        'utm_source' => 'keyhome',
        'utm_medium' => 'qr',
        'utm_campaign' => 'owner_share',
    ];

    $this->postJson('/api/v1/track/visit', [...$base, 'utm_content' => 'profile_a'])->assertCreated();
    $this->postJson('/api/v1/track/visit', [...$base, 'utm_content' => 'profile_b'])->assertCreated();

    expect(SiteVisit::query()->where('session_id', 'sess-content-split')->count())->toBe(2);
});

test('visit tracking dedupes identical payload within cooldown window', function (): void {
    $payload = [
        'session_id' => 'sess-dedupe-identical',
        'utm_source' => 'keyhome',
        'utm_medium' => 'qr',
        'utm_campaign' => 'owner_share',
        'utm_content' => 'profile_one',
    ];

    $this->postJson('/api/v1/track/visit', $payload)->assertCreated();
    $this->postJson('/api/v1/track/visit', $payload)->assertCreated();

    expect(SiteVisit::query()->where('session_id', 'sess-dedupe-identical')->count())->toBe(1);
});

test('registration copies acquisition from matching session visit', function (): void {
    $city = City::factory()->create();

    SiteVisit::factory()->anonymous()->create([
        'session_id' => 'sess-reg-1',
        'source' => 'paid',
        'referrer_domain' => null,
        'utm_source' => 'facebook',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'ads_q1',
        'utm_content' => null,
        'utm_term' => null,
        'ip_hash' => hash('sha256', '10.0.0.1'),
        'device_type' => 'desktop',
        'visited_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/auth/registerCustomer', [
        'firstname' => 'Ada',
        'lastname' => 'Lovelace',
        'email' => 'ada-utm@example.com',
        'phone_number' => '+237690000001',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
        'city_id' => $city->id,
        'session_id' => 'sess-reg-1',
    ]);

    $response->assertCreated();

    $user = User::query()->where('email', 'ada-utm@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->acquisition_source)->toBe('paid')
        ->and($user->utm_source)->toBe('facebook')
        ->and($user->utm_campaign)->toBe('ads_q1');

    expect(SiteVisit::query()->where('session_id', 'sess-reg-1')->where('user_id', $user->id)->count())->toBe(1);
});

test('registration uses explicit utm payload over session visit', function (): void {
    $city = City::factory()->create();

    SiteVisit::factory()->anonymous()->create([
        'session_id' => 'sess-reg-2',
        'source' => 'social',
        'referrer_domain' => null,
        'utm_source' => 'instagram',
        'utm_medium' => 'social',
        'utm_campaign' => 'old',
        'utm_content' => null,
        'utm_term' => null,
        'ip_hash' => hash('sha256', '10.0.0.2'),
        'device_type' => 'mobile',
        'visited_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/auth/registerCustomer', [
        'firstname' => 'Alan',
        'lastname' => 'Turing',
        'email' => 'alan-utm@example.com',
        'phone_number' => '+237690000002',
        'password' => 'Password123@',
        'confirm_password' => 'Password123@',
        'city_id' => $city->id,
        'session_id' => 'sess-reg-2',
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_campaign' => 'weekly_digest',
    ]);

    $response->assertCreated();

    $user = User::query()->where('email', 'alan-utm@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->acquisition_source)->toBe('email')
        ->and($user->utm_source)->toBe('newsletter')
        ->and($user->utm_campaign)->toBe('weekly_digest');
});
