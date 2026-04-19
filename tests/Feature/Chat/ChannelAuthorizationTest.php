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
    $tenant   = User::factory()->create();
    $landlord = User::factory()->create();
    $ad       = Ad::factory()->create(['user_id' => $landlord->id]);
    $conv     = Conversation::create([
        'ad_id'       => $ad->id,
        'tenant_id'   => $tenant->id,
        'landlord_id' => $landlord->id,
        'status'      => ConversationStatus::Active,
    ]);

    $this->actingAs($tenant)
        ->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => "private-conversation.{$conv->id}",
        ])
        ->assertOk();
});

it('allows the landlord to subscribe to their conversation channel', function (): void {
    $tenant   = User::factory()->create();
    $landlord = User::factory()->create();
    $ad       = Ad::factory()->create(['user_id' => $landlord->id]);
    $conv     = Conversation::create([
        'ad_id'       => $ad->id,
        'tenant_id'   => $tenant->id,
        'landlord_id' => $landlord->id,
        'status'      => ConversationStatus::Active,
    ]);

    $this->actingAs($landlord)
        ->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => "private-conversation.{$conv->id}",
        ])
        ->assertOk();
});

it('blocks a non-participant from subscribing to a private conversation channel', function (): void {
    $tenant   = User::factory()->create();
    $landlord = User::factory()->create();
    $ad       = Ad::factory()->create(['user_id' => $landlord->id]);
    $conv     = Conversation::create([
        'ad_id'       => $ad->id,
        'tenant_id'   => $tenant->id,
        'landlord_id' => $landlord->id,
        'status'      => ConversationStatus::Active,
    ]);

    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => "private-conversation.{$conv->id}",
        ])
        ->assertForbidden();
});

it('returns false for a non-existent conversation channel', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => 'private-conversation.00000000-0000-0000-0000-000000000000',
        ])
        ->assertForbidden();
});
