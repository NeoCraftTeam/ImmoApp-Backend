<?php

declare(strict_types=1);

use App\Models\User;

/*
 * Characterization tests locking the current behaviour of the multi-factor
 * (TOTP app + email OTP) contract methods before they move to
 * Concerns\HasMultiFactorAuthentication. Backed by encrypted casts, so each
 * roundtrip goes through the database.
 */

it('persists and returns the app authentication secret', function (): void {
    $user = User::factory()->create();

    $user->saveAppAuthenticationSecret('TOTPSECRET123');

    expect($user->fresh()->getAppAuthenticationSecret())->toBe('TOTPSECRET123');
});

it('clears the app authentication secret when saving null', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('TOTPSECRET123');

    $user->saveAppAuthenticationSecret(null);

    expect($user->fresh()->getAppAuthenticationSecret())->toBeNull();
});

it('uses the email address as the authenticator holder name', function (): void {
    $user = User::factory()->create(['email' => 'holder@example.com']);

    expect($user->getAppAuthenticationHolderName())->toBe('holder@example.com');
});

it('persists and returns app authentication recovery codes', function (): void {
    $user = User::factory()->create();

    $user->saveAppAuthenticationRecoveryCodes(['aaa-111', 'bbb-222']);

    expect($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe(['aaa-111', 'bbb-222']);
});

it('toggles email authentication on and off', function (): void {
    $user = User::factory()->create();

    expect($user->hasEmailAuthentication())->toBeFalse();

    $user->toggleEmailAuthentication(true);
    expect($user->fresh()->hasEmailAuthentication())->toBeTrue();

    $user->toggleEmailAuthentication(false);
    expect($user->fresh()->hasEmailAuthentication())->toBeFalse();
});
