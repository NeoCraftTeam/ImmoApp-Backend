<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('app:expire-boosted-ads resets is_boosted/boost_score for past boosts', function (): void {
    $owner = User::factory()->agents()->create();

    $expired = Ad::factory()->for($owner)->create([
        'is_boosted' => true,
        'boost_score' => 50,
        'boost_expires_at' => now()->subDay(),
    ]);

    $stillActive = Ad::factory()->for($owner)->create([
        'is_boosted' => true,
        'boost_score' => 80,
        'boost_expires_at' => now()->addDays(2),
    ]);

    $this->artisan('app:expire-boosted-ads')->assertSuccessful();

    $expired->refresh();
    $stillActive->refresh();

    expect($expired->is_boosted)->toBeFalse();
    expect((int) $expired->boost_score)->toBe(0);
    expect($stillActive->is_boosted)->toBeTrue();
    expect((int) $stillActive->boost_score)->toBe(80);
});
