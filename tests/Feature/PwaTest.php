<?php

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ─── Manifest & Static Assets ─────────────────────────────

it('manifest route returns valid JSON manifest', function (): void {
    $response = $this->get('/manifest.json');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('lang', 'fr');

    $manifest = $response->json();
    expect($manifest)
        ->toHaveKey('start_url')
        ->toHaveKey('scope')
        ->toHaveKey('icons');

    expect($manifest['start_url'])->toContain('http');
});

it('offline page exists in public directory', function (): void {
    expect(file_exists(public_path('pwa/offline.html')))->toBeTrue();

    $content = file_get_contents(public_path('pwa/offline.html'));
    expect($content)->toContain('Hors ligne');
});

it('service worker exists in public directory', function (): void {
    expect(file_exists(public_path('sw.js')))->toBeTrue();

    $content = file_get_contents(public_path('sw.js'));
    expect($content)->toContain('const CACHE_VERSION = "keyhome-v');
});

it('PWA icons exist for all required sizes', function (): void {
    $sizes = [72, 96, 128, 144, 152, 192, 384, 512];

    foreach ($sizes as $size) {
        expect(file_exists(public_path("pwa/icons/icon-{$size}x{$size}.png")))->toBeTrue();
    }
});

// ─── Push Subscription ────────────────────────────────────

it('requires authentication to subscribe to push', function (): void {
    $response = $this->postJson('/api/v1/pwa/push/subscribe', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
        'keys' => [
            'p256dh' => base64_encode('test-p256dh-key'),
            'auth' => base64_encode('test-auth-token'),
        ],
    ]);

    $response->assertUnauthorized();
});

it('can subscribe to push notifications', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/pwa/push/subscribe', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
        'keys' => [
            'p256dh' => base64_encode('test-p256dh-key'),
            'auth' => base64_encode('test-auth-token'),
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Abonnement push enregistré.');

    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_type' => User::class,
        'subscribable_id' => $user->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
    ]);
});

it('updates existing push subscription instead of duplicating', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint-456';

    PushSubscription::factory()->create([
        'subscribable_type' => User::class,
        'subscribable_id' => $user->id,
        'endpoint' => $endpoint,
        'public_key' => 'old-key',
        'auth_token' => 'old-token',
    ]);

    $response = $this->postJson('/api/v1/pwa/push/subscribe', [
        'endpoint' => $endpoint,
        'keys' => [
            'p256dh' => base64_encode('new-p256dh-key'),
            'auth' => base64_encode('new-auth-token'),
        ],
    ]);

    $response->assertOk();

    $this->assertDatabaseCount('push_subscriptions', 1);
    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_type' => User::class,
        'subscribable_id' => $user->id,
        'endpoint' => $endpoint,
        'public_key' => base64_encode('new-p256dh-key'),
    ]);
});

it('validates push subscribe request', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/pwa/push/subscribe', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

it('can unsubscribe from push notifications', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint-789';

    PushSubscription::factory()->create([
        'subscribable_type' => User::class,
        'subscribable_id' => $user->id,
        'endpoint' => $endpoint,
    ]);

    $response = $this->postJson('/api/v1/pwa/push/unsubscribe', [
        'endpoint' => $endpoint,
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Abonnement push supprimé.');

    $this->assertDatabaseMissing('push_subscriptions', [
        'subscribable_id' => $user->id,
        'endpoint' => $endpoint,
    ]);
});

it('does not delete other users subscriptions on unsubscribe', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $endpoint = 'https://fcm.googleapis.com/fcm/send/shared-endpoint';

    PushSubscription::factory()->create(['subscribable_type' => User::class, 'subscribable_id' => $user1->id, 'endpoint' => $endpoint]);
    PushSubscription::factory()->create(['subscribable_type' => User::class, 'subscribable_id' => $user2->id, 'endpoint' => $endpoint.'-other']);

    Sanctum::actingAs($user1);

    $this->postJson('/api/v1/pwa/push/unsubscribe', ['endpoint' => $endpoint]);

    $this->assertDatabaseCount('push_subscriptions', 1);
    $this->assertDatabaseHas('push_subscriptions', ['subscribable_id' => $user2->id]);
});

// ─── Session Validation ───────────────────────────────────

it('returns valid false when no session', function (): void {
    $response = $this->getJson('/api/v1/pwa/session/validate');

    $response->assertUnauthorized()
        ->assertJsonPath('valid', false);
});

it('returns valid true when authenticated via web session', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')
        ->getJson('/api/v1/pwa/session/validate');

    $response->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('user', $user->id);
});

// ─── PushSubscription Model ──────────────────────────────

it('push subscription belongs to a user', function (): void {
    $subscription = PushSubscription::factory()->create();

    expect($subscription->subscribable)->toBeInstanceOf(User::class);
});

it('user has many push subscriptions', function (): void {
    $user = User::factory()->create();
    PushSubscription::factory()->count(3)->create(['subscribable_type' => User::class, 'subscribable_id' => $user->id]);

    expect($user->pushSubscriptions)->toHaveCount(3);
});
