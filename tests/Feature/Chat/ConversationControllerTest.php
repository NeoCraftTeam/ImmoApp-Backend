<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['chat.encryption_key' => bin2hex(random_bytes(32))]);
    Event::fake(); // Prevent Reverb connection attempts during tests
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeConversationParticipants(): array
{
    $tenant   = User::factory()->create();
    $landlord = User::factory()->create();
    $ad       = Ad::factory()->create(['user_id' => $landlord->id]);

    UnlockedAd::create([
        'user_id'      => $tenant->id,
        'ad_id'        => $ad->id,
        'unlocked_at'  => now(),
    ]);

    $conversation = Conversation::create([
        'ad_id'       => $ad->id,
        'tenant_id'   => $tenant->id,
        'landlord_id' => $landlord->id,
        'status'      => ConversationStatus::Active,
    ]);

    return compact('tenant', 'landlord', 'ad', 'conversation');
}

// ─── GET /conversations ────────────────────────────────────────────────────────

it('returns conversations for the authenticated user', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $this->actingAs($tenant)
        ->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $conv->id);
});

it('does not return conversations belonging to other users', function (): void {
    makeConversationParticipants();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data', []);
});

// ─── POST /conversations ───────────────────────────────────────────────────────

it('creates a conversation after the ad is unlocked', function (): void {
    $tenant   = User::factory()->create();
    $landlord = User::factory()->create();
    $ad       = Ad::factory()->create(['user_id' => $landlord->id]);

    UnlockedAd::create([
        'user_id'     => $tenant->id,
        'ad_id'       => $ad->id,
        'unlocked_at' => now(),
    ]);

    $this->actingAs($tenant)
        ->postJson('/api/v1/conversations', ['ad_id' => $ad->id])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'active');
});

it('returns 200 (not 201) for an existing conversation', function (): void {
    ['tenant' => $tenant, 'ad' => $ad] = makeConversationParticipants();

    $this->actingAs($tenant)
        ->postJson('/api/v1/conversations', ['ad_id' => $ad->id])
        ->assertStatus(200);
});

it('returns 403 when the ad has not been unlocked', function (): void {
    $tenant   = User::factory()->create();
    $landlord = User::factory()->create();
    $ad       = Ad::factory()->create(['user_id' => $landlord->id]);

    $this->actingAs($tenant)
        ->postJson('/api/v1/conversations', ['ad_id' => $ad->id])
        ->assertStatus(403);
});

// ─── IDOR prevention ──────────────────────────────────────────────────────────

it('returns 404 (not 403) when accessing another user\'s conversation', function (): void {
    ['conversation' => $conv] = makeConversationParticipants();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->getJson("/api/v1/conversations/{$conv->id}")
        ->assertNotFound();
});

// ─── GET /conversations/{uuid}/messages ───────────────────────────────────────

it('returns message history for a participant', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $this->actingAs($tenant)
        ->getJson("/api/v1/conversations/{$conv->id}/messages")
        ->assertOk()
        ->assertJsonStructure(['data', 'next_cursor', 'has_more']);
});

// ─── POST /conversations/{uuid}/messages ──────────────────────────────────────

it('sends a message in a conversation', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'body' => 'Bonjour, le logement est-il toujours disponible ?',
        ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Bonjour, le logement est-il toujours disponible ?');
});

it('does not expose the encrypted body or IV in the response', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $response = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'Secret message'])
        ->assertCreated()
        ->json('data');

    expect($response)->not->toHaveKey('body_iv')
        ->and(array_keys($response))->not->toContain('body_iv');
});

it('rejects a message longer than 5000 characters', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'body' => str_repeat('a', 5001),
        ])
        ->assertUnprocessable();
});

it('non-participant cannot send a message', function (): void {
    ['conversation' => $conv] = makeConversationParticipants();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'Hi'])
        ->assertNotFound();
});

// ─── DELETE /messages/{uuid} ──────────────────────────────────────────────────

it('sender can delete their own message', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $msgResponse = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'To delete'])
        ->assertCreated();

    $uuid = $msgResponse->json('data.uuid');

    $this->actingAs($tenant)
        ->deleteJson("/api/v1/messages/{$uuid}")
        ->assertNoContent();
});

it('recipient cannot delete the sender\'s message', function (): void {
    ['tenant' => $tenant, 'landlord' => $landlord, 'conversation' => $conv]
        = makeConversationParticipants();

    $uuid = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'Hi landlord'])
        ->assertCreated()
        ->json('data.uuid');

    $this->actingAs($landlord)
        ->deleteJson("/api/v1/messages/{$uuid}")
        ->assertForbidden();
});

// ─── PATCH /conversations/{uuid}/read ─────────────────────────────────────────

it('marks a conversation as read', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $this->actingAs($tenant)
        ->patchJson("/api/v1/conversations/{$conv->id}/read")
        ->assertOk()
        ->assertJsonStructure(['tenant_last_read_at']);
});

// ─── PATCH /conversations/{uuid}/archive ──────────────────────────────────────

it('archives a conversation', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $this->actingAs($tenant)
        ->patchJson("/api/v1/conversations/{$conv->id}/archive")
        ->assertOk()
        ->assertJsonPath('status', 'archived');
});

// ─── GET /conversations/unread-count ──────────────────────────────────────────

it('returns unread count for authenticated user', function (): void {
    ['tenant' => $tenant] = makeConversationParticipants();

    $this->actingAs($tenant)
        ->getJson('/api/v1/conversations/unread-count')
        ->assertOk()
        ->assertJsonStructure(['total', 'conversations']);
});
