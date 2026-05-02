<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Events\Chat\MessageSent;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['chat.encryption_key' => bin2hex(random_bytes(32))]);
});

/**
 * @return array{public: string}
 */
function rsa2048PublicPem(): array
{
    $res = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    if ($res === false) {
        throw new RuntimeException('openssl_pkey_new failed');
    }
    $details = openssl_pkey_get_details($res);
    if ($details === false || !isset($details['key'])) {
        throw new RuntimeException('openssl_pkey_get_details failed');
    }

    return ['public' => $details['key']];
}

function chatTrioWithE2eeKeys(): array
{
    $keysT = rsa2048PublicPem();
    $keysL = rsa2048PublicPem();

    $tenant = User::factory()->create([
        'chat_e2ee_public_key_pem' => $keysT['public'],
    ]);
    $landlord = User::factory()->create([
        'chat_e2ee_public_key_pem' => $keysL['public'],
    ]);
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);

    UnlockedAd::create([
        'user_id' => $tenant->id,
        'ad_id' => $ad->id,
        'unlocked_at' => now(),
    ]);

    $conversation = Conversation::create([
        'ad_id' => $ad->id,
        'tenant_id' => $tenant->id,
        'landlord_id' => $landlord->id,
        'status' => ConversationStatus::Active,
    ]);

    return compact('tenant', 'landlord', 'ad', 'conversation');
}

it('registers and returns chat E2EE public key', function (): void {
    $user = User::factory()->create(['chat_e2ee_public_key_pem' => null]);
    $pem = rsa2048PublicPem()['public'];

    $this->actingAs($user)
        ->getJson('/api/v1/my/chat-e2ee/public-key')
        ->assertOk()
        ->assertJson(['public_key_pem' => null]);

    $this->actingAs($user)
        ->putJson('/api/v1/my/chat-e2ee/public-key', ['public_key_pem' => $pem])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $roundTrip = (string) $this->actingAs($user)
        ->getJson('/api/v1/my/chat-e2ee/public-key')
        ->assertOk()
        ->json('public_key_pem');

    expect(rtrim($roundTrip))->toBe(rtrim($pem));
    expect(rtrim((string) $user->fresh()->chat_e2ee_public_key_pem))->toBe(rtrim($pem));
});

it('rejects an invalid E2EE public key PEM', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/v1/my/chat-e2ee/public-key', ['public_key_pem' => 'not a key'])
        ->assertUnprocessable();
});

it('stores the first sealed message with wrapped keys and rejects duplicate wrapped keys', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = chatTrioWithE2eeKeys();

    $payload = [
        'is_client_sealed' => true,
        'e2ee_ciphertext_b64' => base64_encode('fakecipher'),
        'e2ee_iv_b64' => base64_encode(random_bytes(12)),
        'e2ee_wrapped_keys' => [
            'tenant' => 'd-tenant',
            'landlord' => 'd-landlord',
        ],
    ];

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", $payload)
        ->assertCreated();

    $conv->refresh();
    expect($conv->e2ee_wrapped_key_tenant)->toBe('d-tenant')
        ->and($conv->e2ee_wrapped_key_landlord)->toBe('d-landlord');

    $payload2 = [
        'is_client_sealed' => true,
        'e2ee_ciphertext_b64' => base64_encode('second'),
        'e2ee_iv_b64' => base64_encode(random_bytes(12)),
        'e2ee_wrapped_keys' => [
            'tenant' => 'x',
            'landlord' => 'y',
        ],
    ];

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", $payload2)
        ->assertStatus(422);
});

it('accepts a follow-up sealed message without wrapped keys', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = chatTrioWithE2eeKeys();

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'is_client_sealed' => true,
            'e2ee_ciphertext_b64' => base64_encode('one'),
            'e2ee_iv_b64' => base64_encode(random_bytes(12)),
            'e2ee_wrapped_keys' => [
                'tenant' => 'a',
                'landlord' => 'b',
            ],
        ])
        ->assertCreated();

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'is_client_sealed' => true,
            'e2ee_ciphertext_b64' => base64_encode('two'),
            'e2ee_iv_b64' => base64_encode(random_bytes(12)),
        ])
        ->assertCreated();
});

it('rejects sealed messages when a participant has no E2EE public key', function (): void {
    $tenant = User::factory()->create([
        'chat_e2ee_public_key_pem' => rsa2048PublicPem()['public'],
    ]);
    $landlord = User::factory()->create(['chat_e2ee_public_key_pem' => null]);
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);

    UnlockedAd::create([
        'user_id' => $tenant->id,
        'ad_id' => $ad->id,
        'unlocked_at' => now(),
    ]);

    $conv = Conversation::create([
        'ad_id' => $ad->id,
        'tenant_id' => $tenant->id,
        'landlord_id' => $landlord->id,
        'status' => ConversationStatus::Active,
    ]);

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'is_client_sealed' => true,
            'e2ee_ciphertext_b64' => base64_encode('x'),
            'e2ee_iv_b64' => base64_encode(random_bytes(12)),
            'e2ee_wrapped_keys' => [
                'tenant' => 'a',
                'landlord' => 'b',
            ],
        ])
        ->assertStatus(422);
});

it('broadcasts MessageSent without plaintext body when client-sealed', function (): void {
    Event::fake([MessageSent::class]);

    ['tenant' => $tenant, 'conversation' => $conv] = chatTrioWithE2eeKeys();

    $cipher = base64_encode('opaque');

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'is_client_sealed' => true,
            'e2ee_ciphertext_b64' => $cipher,
            'e2ee_iv_b64' => base64_encode(random_bytes(12)),
            'e2ee_wrapped_keys' => [
                'tenant' => 'wk-t',
                'landlord' => 'wk-l',
            ],
        ])
        ->assertCreated();

    Event::assertDispatched(MessageSent::class, function (MessageSent $e) use ($cipher): bool {
        $payload = $e->broadcastWith();

        return $payload['is_client_sealed'] === true
            && $payload['body'] === null
            && ($payload['e2ee']['ciphertext_b64'] ?? null) === $cipher
            && isset($payload['e2ee']['iv_b64']);
    });
});

it('includes tenant and landlord E2EE public keys on conversation resource', function (): void {
    ['tenant' => $tenant, 'landlord' => $landlord, 'conversation' => $conv] = chatTrioWithE2eeKeys();

    $this->actingAs($tenant)
        ->getJson("/api/v1/conversations/{$conv->id}")
        ->assertOk()
        ->assertJsonPath('data.e2ee.tenant_public_key_pem', $tenant->chat_e2ee_public_key_pem)
        ->assertJsonPath('data.e2ee.landlord_public_key_pem', $landlord->chat_e2ee_public_key_pem);
});
