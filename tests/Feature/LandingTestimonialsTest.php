<?php

use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns testimonials with meta', function (): void {
    Review::factory()->count(3)->create([
        'rating' => 5,
        'comment' => 'Excellent service !',
    ]);

    $response = $this->getJson('/api/v1/stats/testimonials');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'display_name', 'initials', 'role', 'rating', 'comment', 'created_at'],
            ],
            'meta' => ['average_rating', 'total_count'],
        ]);
});

it('only returns reviews with rating >= 4 and a comment', function (): void {
    Review::factory()->create(['rating' => 5, 'comment' => 'Très bien.']);
    Review::factory()->create(['rating' => 2, 'comment' => 'Pas terrible.']);
    Review::factory()->create(['rating' => 5, 'comment' => null]);

    $response = $this->getJson('/api/v1/stats/testimonials');

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['rating'])->toBeGreaterThanOrEqual(4);
    expect($data[0]['comment'])->not->toBeNull();
});

it('returns a maximum of 8 testimonials', function (): void {
    Review::factory()->count(10)->create([
        'rating' => 5,
        'comment' => 'Super !',
    ]);

    $response = $this->getJson('/api/v1/stats/testimonials');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(8);
});

it('returns correct meta total count and average rating', function (): void {
    Review::factory()->create(['rating' => 4, 'comment' => 'Bien.']);
    Review::factory()->create(['rating' => 5, 'comment' => 'Super.']);

    $response = $this->getJson('/api/v1/stats/testimonials');

    $response->assertOk();
    expect($response->json('meta.total_count'))->toBe(2);
    expect($response->json('meta.average_rating'))->toBe(4.5);
});

it('anonymises user names correctly', function (): void {
    $review = Review::factory()->create([
        'rating' => 5,
        'comment' => 'Parfait !',
    ]);

    $review->user->update(['firstname' => 'Jean', 'lastname' => 'Dupont']);

    $response = $this->getJson('/api/v1/stats/testimonials');

    $response->assertOk();
    $displayName = $response->json('data.0.display_name');
    expect($displayName)->toBe('Jean D.');
    expect($displayName)->not->toContain('Dupont');
});

it('returns empty data array when no qualifying reviews exist', function (): void {
    Review::factory()->count(3)->create(['rating' => 2, 'comment' => 'Nul.']);

    $response = $this->getJson('/api/v1/stats/testimonials');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('is accessible without authentication', function (): void {
    $response = $this->getJson('/api/v1/stats/testimonials');

    $response->assertOk();
});
