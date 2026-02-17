<?php

use App\Enums\AdStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Ad;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('AdStatus defines correct allowed transitions', function (): void {
    // PENDING can only go to AVAILABLE
    expect(AdStatus::PENDING->allowedTransitions())->toBe([AdStatus::AVAILABLE]);

    // AVAILABLE can go to RESERVED, RENT, SOLD
    expect(AdStatus::AVAILABLE->allowedTransitions())->toBe([AdStatus::RESERVED, AdStatus::RENT, AdStatus::SOLD]);

    // RESERVED can go back to AVAILABLE or forward to RENT, SOLD
    expect(AdStatus::RESERVED->allowedTransitions())->toBe([AdStatus::AVAILABLE, AdStatus::RENT, AdStatus::SOLD]);

    // RENT and SOLD can only go back to AVAILABLE
    expect(AdStatus::RENT->allowedTransitions())->toBe([AdStatus::AVAILABLE]);
    expect(AdStatus::SOLD->allowedTransitions())->toBe([AdStatus::AVAILABLE]);
});

test('canTransitionTo returns correct boolean', function (): void {
    expect(AdStatus::PENDING->canTransitionTo(AdStatus::AVAILABLE))->toBeTrue();
    expect(AdStatus::PENDING->canTransitionTo(AdStatus::SOLD))->toBeFalse();
    expect(AdStatus::AVAILABLE->canTransitionTo(AdStatus::RESERVED))->toBeTrue();
    expect(AdStatus::AVAILABLE->canTransitionTo(AdStatus::PENDING))->toBeFalse();
    expect(AdStatus::SOLD->canTransitionTo(AdStatus::AVAILABLE))->toBeTrue();
    expect(AdStatus::SOLD->canTransitionTo(AdStatus::RENT))->toBeFalse();
});

test('Ad can transition from PENDING to AVAILABLE', function (): void {
    $ad = Ad::factory()->create(['status' => AdStatus::PENDING]);

    $ad->transitionTo(AdStatus::AVAILABLE);

    expect($ad->fresh()->status)->toBe(AdStatus::AVAILABLE);
});

test('Ad cannot transition from PENDING to SOLD', function (): void {
    $ad = Ad::factory()->create(['status' => AdStatus::PENDING]);

    $ad->transitionTo(AdStatus::SOLD);
})->throws(InvalidStatusTransitionException::class);

test('Ad can transition from AVAILABLE to RESERVED', function (): void {
    $ad = Ad::factory()->create(['status' => AdStatus::AVAILABLE]);

    $ad->transitionTo(AdStatus::RESERVED);

    expect($ad->fresh()->status)->toBe(AdStatus::RESERVED);
});

test('Ad cannot transition from AVAILABLE back to PENDING', function (): void {
    $ad = Ad::factory()->create(['status' => AdStatus::AVAILABLE]);

    $ad->transitionTo(AdStatus::PENDING);
})->throws(InvalidStatusTransitionException::class);

test('transitionTo is a no-op when status is unchanged', function (): void {
    $ad = Ad::factory()->create(['status' => AdStatus::AVAILABLE]);

    // Should not throw and should not trigger a save
    $ad->transitionTo(AdStatus::AVAILABLE);

    expect($ad->fresh()->status)->toBe(AdStatus::AVAILABLE);
});
