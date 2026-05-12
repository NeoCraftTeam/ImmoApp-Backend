<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use App\Exceptions\Chat\ConversationNotAllowedException;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\UnlockedAd;
use App\Models\User;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['chat.encryption_key' => bin2hex(random_bytes(32))]);
});

it('returns the existing conversation even after the unlock has been soft-deleted', function (): void {
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);

    $unlock = UnlockedAd::create([
        'user_id' => $tenant->id,
        'ad_id' => $ad->id,
        'unlocked_at' => now(),
    ]);

    // Create the conversation while the unlock is active
    $conv = Conversation::create([
        'ad_id' => $ad->id,
        'tenant_id' => $tenant->id,
        'landlord_id' => $landlord->id,
        'status' => ConversationStatus::Active,
    ]);

    // Simulate the unlock being later soft-deleted (refund / expiration)
    $unlock->delete();

    $service = app(ConversationService::class);
    $result = $service->findOrCreate($ad->id, $tenant->id, $landlord->id);

    expect($result->id)->toBe($conv->id);
});

it('reactivates an archived conversation when the tenant tries to reopen it', function (): void {
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
        'status' => ConversationStatus::Archived,
    ]);

    $service = app(ConversationService::class);
    $result = $service->findOrCreate($ad->id, $tenant->id, $landlord->id);

    expect($result->id)->toBe($conv->id);
    expect($result->fresh()->status)->toBe(ConversationStatus::Active);
});

it('still requires an active unlock when no conversation exists yet', function (): void {
    $tenant = User::factory()->create();
    $landlord = User::factory()->create();
    $ad = Ad::factory()->create(['user_id' => $landlord->id]);

    $service = app(ConversationService::class);

    expect(fn () => $service->findOrCreate($ad->id, $tenant->id, $landlord->id))
        ->toThrow(ConversationNotAllowedException::class);
});
