<?php

declare(strict_types=1);

use App\Models\Ad;
use App\Models\TentativeReservation;
use App\Models\User;

// ─────────────────────────────────────────────────────────────────────────────
// PATCH /my/ads/{ad}/prescreening — landlord sets questions
// ─────────────────────────────────────────────────────────────────────────────

it('landlord can set prescreening questions on their ad', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    $response = $this->actingAs($owner, 'sanctum')->patchJson("/api/v1/my/ads/{$ad->id}/prescreening", [
        'questions' => ['Quelle est votre situation professionnelle ?', 'Avez-vous des animaux ?'],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.prescreening_questions.0', 'Quelle est votre situation professionnelle ?')
        ->assertJsonPath('data.prescreening_questions.1', 'Avez-vous des animaux ?');

    expect($ad->fresh()->prescreening_questions)->toBe(['Quelle est votre situation professionnelle ?', 'Avez-vous des animaux ?']);
});

it('landlord cannot set prescreening questions on another owner\'s ad', function (): void {
    $owner = User::factory()->agents()->create();
    $other = User::factory()->agents()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    $this->actingAs($other, 'sanctum')->patchJson("/api/v1/my/ads/{$ad->id}/prescreening", [
        'questions' => ['Test ?'],
    ])->assertForbidden();
});

it('prescreening questions validation rejects more than 10 questions', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    $this->actingAs($owner, 'sanctum')->patchJson("/api/v1/my/ads/{$ad->id}/prescreening", [
        'questions' => array_fill(0, 11, 'Question ?'),
    ])->assertUnprocessable();
});

it('prescreening questions validation rejects empty questions array', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
    });

    $this->actingAs($owner, 'sanctum')->patchJson("/api/v1/my/ads/{$ad->id}/prescreening", [
        'questions' => [],
    ])->assertUnprocessable();
});

// ─────────────────────────────────────────────────────────────────────────────
// DELETE /my/ads/{ad}/prescreening — landlord clears questions
// ─────────────────────────────────────────────────────────────────────────────

it('landlord can clear prescreening questions', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create([
            'user_id' => $owner->id,
            'prescreening_questions' => ['Question existante ?'],
        ]);
    });

    $this->actingAs($owner, 'sanctum')->deleteJson("/api/v1/my/ads/{$ad->id}/prescreening")
        ->assertOk()
        ->assertJsonPath('data.prescreening_questions', []);

    expect($ad->fresh()->prescreening_questions)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// AdResource exposes prescreening_questions
// ─────────────────────────────────────────────────────────────────────────────

it('AdResource exposes prescreening_questions on the ad detail endpoint', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create([
            'user_id' => $owner->id,
            'status' => 'available',
            'prescreening_questions' => ['Avez-vous un garant ?'],
        ]);
    });

    $this->getJson("/api/v1/ads/{$ad->id}")
        ->assertOk()
        ->assertJsonPath('data.prescreening_questions.0', 'Avez-vous un garant ?');
});

it('AdResource returns empty array when no prescreening questions', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = null;
    Ad::withoutSyncingToSearch(function () use (&$ad, $owner): void {
        $ad = Ad::factory()->create([
            'user_id' => $owner->id,
            'status' => 'available',
            'prescreening_questions' => null,
        ]);
    });

    $this->getJson("/api/v1/ads/{$ad->id}")
        ->assertOk()
        ->assertJsonPath('data.prescreening_questions', []);
});

// ─────────────────────────────────────────────────────────────────────────────
// prescreening_answers persists on TentativeReservation model
// ─────────────────────────────────────────────────────────────────────────────

it('TentativeReservation casts prescreening_answers to array', function (): void {
    $owner = User::factory()->agents()->create();
    $client = User::factory()->customers()->create();

    $reservation = null;
    Ad::withoutSyncingToSearch(function () use (&$reservation, $owner, $client): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
        $reservation = TentativeReservation::factory()->create([
            'ad_id' => $ad->id,
            'client_id' => $client->id,
            'prescreening_answers' => ['CDI dans une entreprise tech.'],
        ]);
    });

    $fresh = $reservation->fresh();
    expect($fresh->prescreening_answers)->toBe(['CDI dans une entreprise tech.']);
});

// ─────────────────────────────────────────────────────────────────────────────
// TentativeReservationResource exposes prescreening_answers
// ─────────────────────────────────────────────────────────────────────────────

it('TentativeReservationResource exposes prescreening_answers in landlord listing', function (): void {
    $owner = User::factory()->agents()->create();
    $client = User::factory()->customers()->create();

    $reservation = null;
    Ad::withoutSyncingToSearch(function () use (&$reservation, $owner, $client): void {
        $ad = Ad::factory()->create(['user_id' => $owner->id]);
        $reservation = TentativeReservation::factory()->create([
            'ad_id' => $ad->id,
            'client_id' => $client->id,
            'prescreening_answers' => ['Salarie en CDI.'],
        ]);
    });

    $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/my/viewing-reservations');
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('id', $reservation->id);

    expect($item['prescreening_answers'])->toBe(['Salarie en CDI.']);
});
