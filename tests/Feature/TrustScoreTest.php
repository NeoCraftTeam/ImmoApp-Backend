<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ReservationStatus;
use App\Enums\TrustScoreTier;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Ad;
use App\Models\Document;
use App\Models\Payment;
use App\Models\TentativeReservation;
use App\Models\TrustScore;
use App\Models\User;
use App\Services\Trust\TrustScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/** Helper to create an agent user with required type field. */
function createAgent(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'role' => UserRole::AGENT,
        'type' => UserType::INDIVIDUAL,
        // Default to consented for service-exercising tests; individual tests
        // explicitly override (see "non-consented" tests below).
        'trust_score_consent' => true,
    ], $attrs));
}

/** Helper to create a consented customer for service tests. */
function createConsentedCustomer(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'role' => UserRole::CUSTOMER,
        'trust_score_consent' => true,
    ], $attrs));
}

// ── Service Tests ─────────────────────────────────────────────────────────────

test('trust score service computes tenant score for customer', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'email_verified_at' => now(),
        'trust_score_consent' => true,
    ]);

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);
    $result = $service->compute($user);

    expect($result)
        ->toHaveKeys(['score', 'tier', 'breakdown', 'label'])
        ->and($result['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100)
        ->and($result['tier'])->toBeInstanceOf(TrustScoreTier::class)
        ->and($result['breakdown'])->toHaveKeys([
            'payment_reliability',
            'viewing_attendance',
            'profile_completeness',
            'reviews',
            'account_maturity',
            'documents',
            'verification',
        ]);
});

test('trust score service computes landlord score for agent', function (): void {
    $user = createAgent(['email_verified_at' => now()]);

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);
    $result = $service->compute($user);

    expect($result)
        ->toHaveKeys(['score', 'tier', 'breakdown', 'label'])
        ->and($result['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100)
        ->and($result['breakdown'])->toHaveKeys([
            'ad_quality',
            'response_rate',
            'reviews',
            'profile_completeness',
            'lease_completion',
            'account_maturity',
            'verification',
        ]);
});

test('trust score persists to database after compute', function (): void {
    $user = createConsentedCustomer();

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);
    $service->compute($user);

    $this->assertDatabaseHas('trust_scores', [
        'user_id' => $user->id,
        'role_context' => 'tenant',
    ]);
});

test('trust score is cached after compute', function (): void {
    $user = createConsentedCustomer();

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);
    $result = $service->compute($user);

    $cached = Cache::get("trust_score:{$user->id}:tenant");
    expect($cached)->not->toBeNull()
        ->and($cached['score'])->toBe($result['score']);
});

test('trust score invalidate clears cache', function (): void {
    $user = createConsentedCustomer();

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);
    $service->compute($user);

    expect(Cache::has("trust_score:{$user->id}:tenant"))->toBeTrue();

    $service->invalidate($user);

    expect(Cache::has("trust_score:{$user->id}:tenant"))->toBeFalse();
});

test('trust score getOrCompute uses cache', function (): void {
    $user = createConsentedCustomer();

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);

    $first = $service->getOrCompute($user);
    $second = $service->getOrCompute($user);

    expect($first['score'])->toBe($second['score']);
});

test('new customer with nothing gets low score', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'email_verified_at' => null,
        'phone_number' => null,
        'bio' => null,
        'avatar' => null,
        'created_at' => now(),
        'trust_score_consent' => true,
    ]);

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);
    $result = $service->compute($user);

    // New user with no activity — should be in NonVerifie or Bronze tier
    expect($result['score'])->toBeLessThan(30);
});

test('verified customer with rich profile gets higher score', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'email_verified_at' => now(),
        'phone_number' => '+237612345678',
        'bio' => 'Locataire sérieux et ponctuel',
        'avatar' => 'https://example.com/avatar.jpg',
        'created_at' => now()->subYear(),
        'trust_score_consent' => true,
    ]);

    // Add documents
    $ad = Ad::factory()->create(['user_id' => $user->id]);
    Document::create([
        'user_id' => $user->id,
        'ad_id' => $ad->id,
        'type' => 'id_card',
        'name' => 'CNI',
        'file_path' => 'docs/cni.pdf',
    ]);

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);
    $result = $service->compute($user);

    // Should be at least Bronze with verification + profile + docs + account age
    expect($result['score'])->toBeGreaterThanOrEqual(25);
});

test('tier mapping is correct', function (): void {
    expect(TrustScoreTier::fromScore(0))->toBe(TrustScoreTier::NonVerifie)
        ->and(TrustScoreTier::fromScore(19))->toBe(TrustScoreTier::NonVerifie)
        ->and(TrustScoreTier::fromScore(20))->toBe(TrustScoreTier::Bronze)
        ->and(TrustScoreTier::fromScore(39))->toBe(TrustScoreTier::Bronze)
        ->and(TrustScoreTier::fromScore(40))->toBe(TrustScoreTier::Argent)
        ->and(TrustScoreTier::fromScore(59))->toBe(TrustScoreTier::Argent)
        ->and(TrustScoreTier::fromScore(60))->toBe(TrustScoreTier::Or)
        ->and(TrustScoreTier::fromScore(79))->toBe(TrustScoreTier::Or)
        ->and(TrustScoreTier::fromScore(80))->toBe(TrustScoreTier::Platine)
        ->and(TrustScoreTier::fromScore(100))->toBe(TrustScoreTier::Platine);
});

test('tier labels are in French', function (): void {
    expect(TrustScoreTier::NonVerifie->label())->toBe('Non vérifié')
        ->and(TrustScoreTier::Bronze->label())->toBe('Bronze')
        ->and(TrustScoreTier::Argent->label())->toBe('Argent')
        ->and(TrustScoreTier::Or->label())->toBe('Or')
        ->and(TrustScoreTier::Platine->label())->toBe('Platine');
});

// ── API Tests ─────────────────────────────────────────────────────────────────

test('authenticated user can get own trust score', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'trust_score_consent' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/my/trust-score');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'score',
                'tier',
                'tier_label',
                'tier_color',
                'breakdown',
                'tips',
            ],
        ]);
});

test('unauthenticated user cannot get own trust score', function (): void {
    $response = $this->getJson('/api/v1/my/trust-score');
    $response->assertUnauthorized();
});

test('trust score requires consent', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'trust_score_consent' => null,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/my/trust-score');

    $response->assertOk()
        ->assertJsonPath('consent_required', true);
});

test('user can grant consent', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'trust_score_consent' => null,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/my/trust-score/consent', [
            'consent' => true,
        ]);

    $response->assertOk();
    expect($user->fresh()->trust_score_consent)->toBeTrue();
});

test('user can revoke consent', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'trust_score_consent' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/my/trust-score/consent', [
            'consent' => false,
        ]);

    $response->assertOk();
    expect($user->fresh()->trust_score_consent)->toBeFalse();
});

test('public trust score endpoint works for consented user', function (): void {
    $user = createAgent(['trust_score_consent' => true]);

    // Compute their score first
    app(TrustScoreService::class)->compute($user);

    $response = $this->getJson("/api/v1/users/{$user->id}/trust-score");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'score',
                'tier',
                'tier_label',
                'tier_color',
            ],
        ]);
});

test('public trust score endpoint returns null for non-consented user', function (): void {
    $user = createAgent(['trust_score_consent' => null]);

    $response = $this->getJson("/api/v1/users/{$user->id}/trust-score");

    $response->assertOk()
        ->assertJson(['data' => null]);
});

// ── Command Tests ─────────────────────────────────────────────────────────────

test('recompute command processes consented users', function (): void {
    User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'trust_score_consent' => true,
    ]);
    createAgent(['trust_score_consent' => true]);
    User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'trust_score_consent' => null,
    ]);

    $this->artisan('trustscore:recompute')
        ->assertExitCode(0);

    // Only 2 consented users should have scores
    expect(TrustScore::count())->toBe(2);
});

// ── Breakdown Signal Tests ────────────────────────────────────────────────────

test('payment reliability signal awards points for successful payments', function (): void {
    $user = createConsentedCustomer();

    // Create successful payments
    for ($i = 0; $i < 5; $i++) {
        Payment::factory()->create([
            'user_id' => $user->id,
            'status' => PaymentStatus::SUCCESS,
            'type' => PaymentType::CREDIT,
        ]);
    }

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);
    $result = $service->compute($user);

    $paymentScore = $result['breakdown']['payment_reliability']['score'];
    expect($paymentScore)->toBeGreaterThan(3); // baseline (no payments) = 3
});

test('viewing attendance signal rewards kept appointments', function (): void {
    $user = createConsentedCustomer();

    // Create confirmed reservations with different slot dates to avoid unique constraint
    for ($i = 0; $i < 5; $i++) {
        $ad = Ad::factory()->create();
        TentativeReservation::factory()->create([
            'client_id' => $user->id,
            'ad_id' => $ad->id,
            'status' => ReservationStatus::Confirmed,
            'slot_date' => now()->addDays($i + 1),
        ]);
    }

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);
    $result = $service->compute($user);

    $viewingScore = $result['breakdown']['viewing_attendance']['score'];
    expect($viewingScore)->toBeGreaterThan(5); // > baseline
});

test('verification signal awards points for email and phone', function (): void {
    $userVerified = User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'email_verified_at' => now(),
        'phone_number' => '+237612345678',
        'trust_score_consent' => true,
    ]);

    $userUnverified = User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'email_verified_at' => null,
        'phone_number' => null,
        'trust_score_consent' => true,
    ]);

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);

    $verified = $service->compute($userVerified);
    $unverified = $service->compute($userUnverified);

    expect($verified['breakdown']['verification']['score'])
        ->toBeGreaterThan($unverified['breakdown']['verification']['score']);
});

test('score never exceeds 100', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::CUSTOMER,
        'email_verified_at' => now(),
        'phone_number' => '+237612345678',
        'bio' => 'Lorem ipsum dolor sit amet',
        'avatar' => 'https://example.com/avatar.jpg',
        'created_at' => now()->subYears(3),
        'trust_score_consent' => true,
    ]);

    /** @var TrustScoreService $service */
    $service = app(TrustScoreService::class);
    $result = $service->compute($user);

    expect($result['score'])->toBeLessThanOrEqual(100);
});
