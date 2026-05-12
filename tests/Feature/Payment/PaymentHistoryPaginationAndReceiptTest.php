<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns length-aware pagination meta for payment history', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 4) as $i) {
        Payment::factory()->success()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours($i),
        ]);
    }

    Sanctum::actingAs($user);

    $first = $this->getJson('/api/v1/payments/history?per_page=2')
        ->assertSuccessful();

    expect($first->json('meta'))->toHaveKeys(['current_page', 'last_page', 'per_page', 'total']);
    expect($first->json('meta'))->not->toHaveKey('next_cursor');
    expect($first->json('meta.current_page'))->toBe(1);
    expect($first->json('meta.last_page'))->toBe(2);
    expect($first->json('meta.per_page'))->toBe(2);
    expect($first->json('meta.total'))->toBe(4);
    expect($first->json('data'))->toHaveCount(2);

    $second = $this->getJson('/api/v1/payments/history?page=2&per_page=2')
        ->assertSuccessful();

    expect($second->json('meta.current_page'))->toBe(2);
    expect($second->json('data'))->toHaveCount(2);

    $ids = collect($first->json('data'))->pluck('id')
        ->merge(collect($second->json('data'))->pluck('id'))
        ->unique();

    expect($ids)->toHaveCount(4);
});

it('defaults to ten items per page', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 12) as $i) {
        Payment::factory()->success()->create([
            'user_id' => $user->id,
            'created_at' => now()->subMinutes($i),
        ]);
    }

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/payments/history')
        ->assertSuccessful();

    expect($response->json('meta.per_page'))->toBe(10);
    expect($response->json('meta.total'))->toBe(12);
    expect($response->json('meta.last_page'))->toBe(2);
    expect($response->json('data'))->toHaveCount(10);
});

it('allows downloading an owned payment receipt pdf', function (): void {
    $user = User::factory()->create();

    $payment = Payment::factory()->success()->create([
        'user_id' => $user->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->get('/api/v1/payments/'.$payment->id.'/receipt')
        ->assertSuccessful();

    expect((string) $response->headers->get('content-type'))->toContain('pdf');
    expect((string) $response->headers->get('content-disposition'))->toContain('inline');
});

it('forbids receipt pdf for another users payment', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $payment = Payment::factory()->success()->create([
        'user_id' => $owner->id,
    ]);

    Sanctum::actingAs($other);

    $this->get('/api/v1/payments/'.$payment->id.'/receipt')
        ->assertForbidden();
});
