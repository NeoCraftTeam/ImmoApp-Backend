<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Jobs\MarkConversationReadJob;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UnlockedAd;
use App\Models\User;
use App\Services\Chat\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['chat.encryption_key' => bin2hex(random_bytes(32))]);
    Event::fake(); // Prevent Reverb connection attempts during tests
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeConversationParticipants(): array
{
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
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

// ─── GET /conversations ────────────────────────────────────────────────────────

it('returns conversations for the authenticated user', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $this->actingAs($tenant)
        ->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $conv->id);
});

it('conversation list previews the latest thread message even when last_message_id is stale', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();
    /** @var MessageService $messages */
    $messages = app(MessageService::class);

    $first = $messages->send($conv->fresh(), $tenant, 'hmm');
    $second = $messages->send($conv->fresh(), $tenant, 'newest body');

    Conversation::query()->where('id', $conv->id)->update([
        'last_message_id' => $first->id,
    ]);

    expect($conv->fresh()->last_message_id)->toBe($first->id);

    $this->actingAs($tenant)
        ->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.last_message.uuid', $second->id)
        ->assertJsonPath('data.0.last_message.body', 'newest body');
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
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);

    UnlockedAd::create([
        'user_id' => $tenant->id,
        'ad_id' => $ad->id,
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
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);

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

it('dispatches mark-as-read job only for the first page of message history', function (): void {
    Bus::fake();
    config(['chat.pagination.messages' => 1]);
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'first'])
        ->assertCreated();
    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'second'])
        ->assertCreated();

    $firstPage = $this->actingAs($tenant)
        ->getJson("/api/v1/conversations/{$conv->id}/messages")
        ->assertOk();

    Bus::assertDispatchedTimes(MarkConversationReadJob::class, 1);

    $cursor = $firstPage->json('next_cursor');
    expect($cursor)->not->toBeNull();

    $this->actingAs($tenant)
        ->getJson("/api/v1/conversations/{$conv->id}/messages?cursor=".urlencode((string) $cursor))
        ->assertOk();

    Bus::assertDispatchedTimes(MarkConversationReadJob::class, 1);
});

it('rejects reply_to_id when the message belongs to another conversation', function (): void {
    $tenant = User::factory()->create();
    $landlord1 = User::factory()->create();
    $landlord2 = User::factory()->create();
    $ad1 = Ad::factory()->create(['user_id' => $landlord1->id]);
    $ad2 = Ad::factory()->create(['user_id' => $landlord2->id]);

    foreach ([$ad1, $ad2] as $ad) {
        UnlockedAd::create([
            'user_id' => $tenant->id,
            'ad_id' => $ad->id,
            'unlocked_at' => now(),
        ]);
    }

    $conv1 = Conversation::create([
        'ad_id' => $ad1->id,
        'tenant_id' => $tenant->id,
        'landlord_id' => $landlord1->id,
        'status' => ConversationStatus::Active,
    ]);
    $conv2 = Conversation::create([
        'ad_id' => $ad2->id,
        'tenant_id' => $tenant->id,
        'landlord_id' => $landlord2->id,
        'status' => ConversationStatus::Active,
    ]);

    $foreignReplyId = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv2->id}/messages", ['body' => 'In conv 2'])
        ->assertCreated()
        ->json('data.uuid');

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv1->id}/messages", [
            'body' => 'Wrong thread',
            'reply_to_id' => $foreignReplyId,
        ])
        ->assertUnprocessable();
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

it('sends a voice note with type audio and attachments', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();

    $path = 'chats/'.$conv->id.'/'.fake()->uuid().'.webm';

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'type' => 'audio',
            'attachments' => [[
                'url' => $path,
                'signed_url' => 'https://example.com/signed-voice.webm',
                'original_name' => 'voice.webm',
                'mime_type' => 'audio/webm',
                'size' => 2048,
                'type' => 'audio',
                'audio_duration_ms' => 1500,
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'audio')
        ->assertJsonPath('data.attachments.0.type', 'audio');

    expect(Message::query()->first()?->type->value)->toBe('audio');
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

it('rejects sending a message to an archived conversation', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();
    $conv->update(['status' => ConversationStatus::Archived]);

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'Hi'])
        ->assertUnprocessable();
});

it('rejects attachment upload to an archived conversation', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();
    $conv->update(['status' => ConversationStatus::Archived]);

    $this->actingAs($tenant)
        ->post("/api/v1/conversations/{$conv->id}/attachments", [
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ])
        ->assertUnprocessable();
});

it('accepts voice upload when finfo reports video_webm (MediaRecorder WebM)', function (): void {
    ['tenant' => $tenant, 'conversation' => $conv] = makeConversationParticipants();
    Storage::fake('r2');

    $file = UploadedFile::fake()->create('voice.webm', 8, 'video/webm');

    $this->actingAs($tenant)
        ->post("/api/v1/conversations/{$conv->id}/attachments", [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ])
        ->assertCreated()
        ->assertJsonPath('data.mime_type', 'audio/webm')
        ->assertJsonPath('data.type', 'audio');
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
