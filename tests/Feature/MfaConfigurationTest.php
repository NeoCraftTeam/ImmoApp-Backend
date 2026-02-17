<?php

use Filament\Facades\Filament;

test('admin panel has mfa enabled', function (): void {
    $panel = Filament::getPanel('admin');
    $providers = $panel->getMultiFactorAuthenticationProviders();

    expect($providers)->not->toBeEmpty();
    expect($providers)->toHaveCount(2); // App + Email
});

test('agency panel has mfa enabled', function (): void {
    $panel = Filament::getPanel('agency');
    $providers = $panel->getMultiFactorAuthenticationProviders();

    expect($providers)->not->toBeEmpty();
    expect($providers)->toHaveCount(2); // App + Email
});

test('bailleur panel has mfa enabled', function (): void {
    $panel = Filament::getPanel('bailleur');
    $providers = $panel->getMultiFactorAuthenticationProviders();

    expect($providers)->not->toBeEmpty();
    expect($providers)->toHaveCount(2); // App + Email
});
