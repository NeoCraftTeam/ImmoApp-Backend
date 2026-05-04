<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Events\Chat\ConversationArchived;
use App\Events\Chat\ConversationUnarchived;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageRead;
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
    Event::fake([MessageRead::class]);

    ['tenant' => $tenant, 'conversation' => $conv] = setupChatTrio();

    $this->actingAs($tenant)
        ->patchJson("/api/v1/conversations/{$conv->id}/read")
        ->assertOk();

    Event::assertDispatched(MessageRead::class, fn (MessageRead $event): bool => $event instanceof ShouldBroadcastNow);
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
