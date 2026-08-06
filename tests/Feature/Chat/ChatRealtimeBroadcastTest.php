<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Events\Chat\ConversationArchived;
use App\Events\Chat\ConversationUnarchived;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageRead;
use App\Events\Chat\MessageReceived;
use App\Events\Chat\MessageSent;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['chat.encryption_key' => bin2hex(random_bytes(32))]);
});

function setupChatTrio(): array
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

// ── MessageRead must use ShouldBroadcastNow + toOthers ──────────────────────

it('broadcasts MessageRead immediately (now) to others only', function (): void {
    ['tenant' => $tenant, 'landlord' => $landlord, 'conversation' => $conv] = setupChatTrio();

    // Un message non lu du bailleur pour qu'il y ait quelque chose à marquer.
    $this->actingAs($landlord)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'Bonjour'])
        ->assertCreated();

    Event::fake([MessageRead::class]);

    $this->actingAs($tenant)
        ->patchJson("/api/v1/conversations/{$conv->id}/read")
        ->assertOk();

    Event::assertDispatched(MessageRead::class, fn (MessageRead $event): bool => $event instanceof ShouldBroadcastNow);
});

it('does not broadcast MessageRead when there is nothing to mark read', function (): void {
    Event::fake([MessageRead::class]);

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    // Conversation sans message non lu : lire ne doit rien diffuser (évite
    // le double MessageRead GET /messages + PATCH /read et les broadcasts
    // à vide sur un simple rechargement).
    $this->actingAs($tenant)
        ->patchJson("/api/v1/conversations/{$conv->id}/read")
        ->assertOk();

    Event::assertNotDispatched(MessageRead::class);
});

// ── MessageDeleted broadcasts to others only ────────────────────────────────

it('soft-deleting a message broadcasts MessageDeleted', function (): void {
    Event::fake([MessageDeleted::class]);

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    $msg = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'to delete'])
        ->assertCreated()
        ->json('data');

    $this->actingAs($tenant)
        ->deleteJson("/api/v1/messages/{$msg['uuid']}")
        ->assertNoContent();

    Event::assertDispatched(MessageDeleted::class, fn (MessageDeleted $event): bool => $event->conversationId === $conv->id
        && $event->messageId === $msg['uuid']);
});

// ── last_message_id realignment when the latest message is soft-deleted ─────

it('realigns conversation.last_message_id to the previous message after delete', function (): void {
    Event::fake();

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    // First message
    $first = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'first'])
        ->assertCreated()
        ->json('data');

    sleep(1); // ensure created_at ordering is stable across both rows

    // Second message becomes last_message_id
    $second = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'second'])
        ->assertCreated()
        ->json('data');

    expect($conv->fresh()->last_message_id)->toBe($second['uuid']);

    // Delete the second one — last_message_id must fall back to first
    $this->actingAs($tenant)
        ->deleteJson("/api/v1/messages/{$second['uuid']}")
        ->assertNoContent();

    expect($conv->fresh()->last_message_id)->toBe($first['uuid']);
});

it('sets conversation.last_message_id to null when the only message is deleted', function (): void {
    Event::fake();

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    $msg = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'only one'])
        ->assertCreated()
        ->json('data');

    $this->actingAs($tenant)
        ->deleteJson("/api/v1/messages/{$msg['uuid']}")
        ->assertNoContent();

    expect($conv->fresh()->last_message_id)->toBeNull();
});

// ── ConversationArchived broadcast ──────────────────────────────────────────

it('broadcasts ConversationArchived when a conversation is archived', function (): void {
    Event::fake([ConversationArchived::class]);

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    $this->actingAs($tenant)
        ->patchJson("/api/v1/conversations/{$conv->id}/archive")
        ->assertOk();

    Event::assertDispatched(ConversationArchived::class, fn (ConversationArchived $event): bool => $event->conversationId === $conv->id
        && $event->archivedById === $tenant->id);
});

it('does not broadcast ConversationArchived when conversation is already archived', function (): void {
    Event::fake([ConversationArchived::class]);

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();
    $conv->update(['status' => ConversationStatus::Archived]);

    $this->actingAs($tenant)
        ->patchJson("/api/v1/conversations/{$conv->id}/archive")
        ->assertOk();

    Event::assertNotDispatched(ConversationArchived::class);
});

it('broadcasts ConversationUnarchived when an archived conversation is restored', function (): void {
    Event::fake([ConversationUnarchived::class]);

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();
    $conv->update(['status' => ConversationStatus::Archived]);

    $this->actingAs($tenant)
        ->patchJson("/api/v1/conversations/{$conv->id}/unarchive")
        ->assertOk();

    Event::assertDispatched(ConversationUnarchived::class, fn (ConversationUnarchived $event): bool => $event->conversationId === $conv->id
        && $event->unarchivedById === $tenant->id);
});

it('does not broadcast ConversationUnarchived when conversation is already active', function (): void {
    Event::fake([ConversationUnarchived::class]);

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    $this->actingAs($tenant)
        ->patchJson("/api/v1/conversations/{$conv->id}/unarchive")
        ->assertOk();

    Event::assertNotDispatched(ConversationUnarchived::class);
});

// ── Attachment ownership validation ─────────────────────────────────────────

it('rejects an attachment whose url belongs to another conversation', function (): void {
    Event::fake();

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    $foreignConvId = '00000000-0000-0000-0000-000000000001';

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'body' => '',
            'attachments' => [[
                'url' => "chats/{$foreignConvId}/abc.jpg",
                'signed_url' => 'https://r2.example.com/signed/abc.jpg',
                'original_name' => 'abc.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 1234,
                'type' => 'image',
            ]],
        ])
        ->assertStatus(422);
});

it('accepts an attachment whose url is scoped to the current conversation', function (): void {
    Event::fake();

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'body' => '',
            'type' => 'image',
            'attachments' => [[
                'url' => "chats/{$conv->id}/file.jpg",
                'signed_url' => 'https://r2.example.com/signed/file.jpg',
                'original_name' => 'file.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 1234,
                'type' => 'image',
            ]],
        ])
        ->assertCreated();
});

// ── MessageSent should still use toOthers (regression) ──────────────────────

it('still broadcasts MessageSent on send', function (): void {
    Event::fake([MessageSent::class]);

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'hello'])
        ->assertCreated();

    Event::assertDispatched(MessageSent::class);
});

// ── MessageReceived : signal temps réel sur le canal user.{destinataire} ────

it('broadcasts MessageReceived on the recipient user channel when a message is sent', function (): void {
    // MessageSent est faké aussi : sinon son vrai broadcast tente une
    // connexion Reverb (BROADCAST_CONNECTION=reverb dans .env local).
    Event::fake([MessageSent::class, MessageReceived::class]);

    ['tenant' => $tenant, 'landlord' => $landlord, 'conversation' => $conv] = setupChatTrio();

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'Bonjour, le logement est-il libre ?'])
        ->assertCreated();

    Event::assertDispatched(MessageReceived::class, fn (MessageReceived $event): bool => $event instanceof ShouldBroadcastNow
        && $event->recipientId === (string) $landlord->id
        && $event->broadcastAs() === 'message.received'
        && $event->broadcastOn()[0]->name === "private-user.{$landlord->id}");
});

it('targets the sender user channel when the landlord replies', function (): void {
    Event::fake([MessageSent::class, MessageReceived::class]);

    ['tenant' => $tenant, 'landlord' => $landlord, 'conversation' => $conv] = setupChatTrio();

    $this->actingAs($landlord)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'Oui, disponible de suite'])
        ->assertCreated();

    Event::assertDispatched(MessageReceived::class, fn (MessageReceived $event): bool => $event->recipientId === (string) $tenant->id);
});

it('exposes a truncated preview and sender identity in the MessageReceived payload', function (): void {
    Event::fake([MessageSent::class, MessageReceived::class]);

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    $longBody = str_repeat('Lorem ipsum dolor sit amet. ', 20); // 560 car. > 120

    $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => $longBody])
        ->assertCreated();

    Event::assertDispatched(MessageReceived::class, function (MessageReceived $event) use ($tenant, $conv, $longBody): bool {
        $payload = $event->broadcastWith();

        return $payload['uuid'] !== null
            && $payload['conversation_uuid'] === $conv->id
            && $payload['sender_id'] === $tenant->id
            && $payload['sender']['name'] === trim("{$tenant->firstname} {$tenant->lastname}")
            && $payload['body'] === mb_substr($longBody, 0, 120)
            && $payload['is_client_sealed'] === false
            && $payload['created_at'] !== null;
    });
});
