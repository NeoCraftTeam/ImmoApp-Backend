<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Events\Chat\MessageReactionAdded;
use App\Events\Chat\MessageReactionRemoved;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\MessageReaction;
use App\Models\UnlockedAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['chat.encryption_key' => bin2hex(random_bytes(32))]);
});

function bootstrapMessageReactionFixture(): array
{
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
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

    return compact('tenant', 'landlord', 'conv');
}

// ── POST /messages/{uuid}/reactions ────────────────────────────────────────

it('lets a participant add a reaction to a message', function (): void {
    Event::fake([MessageReactionAdded::class]);

    ['tenant' => $tenant, 'conv' => $conv] = bootstrapMessageReactionFixture();

    $msg = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'hi'])
        ->assertCreated()
        ->json('data');

    $this->actingAs($tenant)
        ->postJson("/api/v1/messages/{$msg['uuid']}/reactions", ['emoji' => '❤️'])
        ->assertCreated();

    expect(MessageReaction::query()->where('message_id', $msg['uuid'])->count())->toBe(1);

    Event::assertDispatched(MessageReactionAdded::class, fn (MessageReactionAdded $event): bool => $event->messageId === $msg['uuid']
        && $event->userId === $tenant->id
        && $event->emoji === '❤️');
});

it('does not duplicate the same reaction from the same user', function (): void {
    Event::fake();

    ['tenant' => $tenant, 'conv' => $conv] = bootstrapMessageReactionFixture();

    $msg = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'hi'])
        ->assertCreated()
        ->json('data');

    $this->actingAs($tenant)
        ->postJson("/api/v1/messages/{$msg['uuid']}/reactions", ['emoji' => '👍'])
        ->assertCreated();
    $this->actingAs($tenant)
        ->postJson("/api/v1/messages/{$msg['uuid']}/reactions", ['emoji' => '👍'])
        ->assertCreated();

    expect(MessageReaction::query()->count())->toBe(1);
});

it('rejects reactions from non-participants with 404', function (): void {
    Event::fake();

    ['tenant' => $tenant, 'conv' => $conv] = bootstrapMessageReactionFixture();
    $outsider = User::factory()->create();

    $msg = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'hi'])
        ->assertCreated()
        ->json('data');

    $this->actingAs($outsider)
        ->postJson("/api/v1/messages/{$msg['uuid']}/reactions", ['emoji' => '❤️'])
        ->assertNotFound();
});

// ── DELETE /messages/{uuid}/reactions ──────────────────────────────────────

it('removes an existing reaction (toggle off)', function (): void {
    Event::fake([MessageReactionRemoved::class]);

    ['tenant' => $tenant, 'conv' => $conv] = bootstrapMessageReactionFixture();

    $msg = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'hi'])
        ->assertCreated()
        ->json('data');

    $this->actingAs($tenant)
        ->postJson("/api/v1/messages/{$msg['uuid']}/reactions", ['emoji' => '🔥']);
    $this->actingAs($tenant)
        ->deleteJson("/api/v1/messages/{$msg['uuid']}/reactions", ['emoji' => '🔥'])
        ->assertNoContent();

    expect(MessageReaction::query()->count())->toBe(0);

    Event::assertDispatched(MessageReactionRemoved::class);
});

it('does not broadcast a removal when the reaction did not exist', function (): void {
    Event::fake([MessageReactionRemoved::class]);

    ['tenant' => $tenant, 'conv' => $conv] = bootstrapMessageReactionFixture();

    $msg = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'hi'])
        ->assertCreated()
        ->json('data');

    $this->actingAs($tenant)
        ->deleteJson("/api/v1/messages/{$msg['uuid']}/reactions", ['emoji' => '🔥'])
        ->assertNoContent();

    Event::assertNotDispatched(MessageReactionRemoved::class);
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects an empty emoji', function (): void {
    Event::fake();

    ['tenant' => $tenant, 'conv' => $conv] = bootstrapMessageReactionFixture();

    $msg = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'hi'])
        ->assertCreated()
        ->json('data');

    $this->actingAs($tenant)
        ->postJson("/api/v1/messages/{$msg['uuid']}/reactions", ['emoji' => ''])
        ->assertUnprocessable();
});

it('rejects an emoji longer than 16 characters', function (): void {
    Event::fake();

    ['tenant' => $tenant, 'conv' => $conv] = bootstrapMessageReactionFixture();

    $msg = $this->actingAs($tenant)
        ->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'hi'])
        ->assertCreated()
        ->json('data');

    $this->actingAs($tenant)
        ->postJson("/api/v1/messages/{$msg['uuid']}/reactions", ['emoji' => str_repeat('a', 17)])
        ->assertUnprocessable();
});
