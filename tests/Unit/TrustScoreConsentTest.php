<?php

declare(strict_types=1);

use App\Exceptions\TrustScoreConsentMissingException;
use App\Models\User;
use App\Services\TrustScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuses compute() when trust_score_consent is null', function (): void {
    $user = User::factory()->create(['trust_score_consent' => null]);

    app(TrustScoreService::class)->compute($user);
})->throws(TrustScoreConsentMissingException::class);

it('refuses compute() when trust_score_consent is false', function (): void {
    $user = User::factory()->create(['trust_score_consent' => false]);

    app(TrustScoreService::class)->compute($user);
})->throws(TrustScoreConsentMissingException::class);

it('computes when trust_score_consent is true', function (): void {
    $user = User::factory()->create(['trust_score_consent' => true]);

    $result = app(TrustScoreService::class)->compute($user);

    expect($result)->toHaveKeys(['score', 'tier', 'breakdown', 'label']);
    expect($result['score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});
