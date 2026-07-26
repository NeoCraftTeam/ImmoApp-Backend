<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Creating a search alert with criteria identical to an existing one must not
 * spawn a duplicate the user would then have to manage twice — the endpoint
 * returns the existing alert instead.
 */
it('returns the existing alert instead of creating a duplicate with identical criteria', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($user);

    $payload = [
        'city_name' => 'Douala',
        'price_max' => 200000,
        'bedrooms_min' => 2,
        'notify_email' => true,
    ];

    $this->postJson('/api/v1/search-alerts', $payload)->assertCreated();

    // Same search criteria (even with a different notification setting) → no new
    // row; the endpoint returns the existing alert with 200.
    $this->postJson('/api/v1/search-alerts', array_merge($payload, ['notify_email' => false]))
        ->assertOk();

    expect($user->searchAlerts()->count())->toBe(1);
});

it('creates separate alerts when the criteria differ', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/search-alerts', ['city_name' => 'Douala', 'price_max' => 200000])
        ->assertCreated();
    $this->postJson('/api/v1/search-alerts', ['city_name' => 'Yaoundé', 'price_max' => 200000])
        ->assertCreated();

    expect($user->searchAlerts()->count())->toBe(2);
});
