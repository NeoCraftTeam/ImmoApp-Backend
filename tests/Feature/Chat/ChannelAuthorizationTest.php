<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['chat.encryption_key' => bin2hex(random_bytes(32))]);
});

it('allows the tenant to subscribe to their conversation channel', function (): void {
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);
    $conv = Conversation::create([
        'ad_id' => $ad->id,
        'tenant_id' => $tenant->id,
        'landlord_id' => $landlord->id,
        'status' => ConversationStatus::Active,
    ]);

    $this->actingAs($tenant)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$conv->id}",
        ])
        ->assertOk();
});

it('allows the landlord to subscribe to their conversation channel', function (): void {
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);
    $conv = Conversation::create([
        'ad_id' => $ad->id,
        'tenant_id' => $tenant->id,
        'landlord_id' => $landlord->id,
        'status' => ConversationStatus::Active,
    ]);

    $this->actingAs($landlord)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$conv->id}",
        ])
        ->assertOk();
});

it('blocks a non-participant from subscribing to a private conversation channel', function (): void {
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);
    $conv = Conversation::create([
        'ad_id' => $ad->id,
        'tenant_id' => $tenant->id,
        'landlord_id' => $landlord->id,
        'status' => ConversationStatus::Active,
    ]);

    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$conv->id}",
        ])
        ->assertForbidden();
});

it('allows the tenant to auth via the API broadcasting route (Sanctum Bearer)', function (): void {
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);
    $conv = Conversation::create([
        'ad_id' => $ad->id,
        'tenant_id' => $tenant->id,
        'landlord_id' => $landlord->id,
        'status' => ConversationStatus::Active,
    ]);

    $this->actingAs($tenant, 'sanctum')
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$conv->id}",
        ])
        ->assertOk();
});

it('returns false for a non-existent conversation channel', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-conversation.00000000-0000-0000-0000-000000000000',
        ])
        ->assertForbidden();
});

it('presence channel authorizes but no longer leaks name or avatar (only id + device)', function (): void {
    $user = User::factory()->create([
        'firstname' => 'Jean',
        'lastname' => 'Dupont',
    ]);

    $response = $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'presence-online-users',
        ])
        ->assertOk();

    // Le channel_data d'un canal de présence contient les infos du membre.
    // Il ne doit plus exposer le nom ni l'avatar (fuite de vie privée) —
    // seulement l'id et le device, ce que les clients consomment.
    $channelData = (string) $response->json('channel_data');
    expect($channelData)->toContain((string) $user->id)
        ->and($channelData)->not->toContain('Jean')
        ->and($channelData)->not->toContain('Dupont')
        ->and($channelData)->not->toContain('avatar');
});
