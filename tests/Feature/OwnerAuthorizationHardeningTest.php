<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Middleware\EnsureTokenMatchesRole;
use App\Models\Agency;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

/**
 * Regression coverage for the owner-panel A01 hardening (audit 2026-05-10):
 *
 *   B1 — Only the agency `owner_id` may invite new team members.
 *   B2 — Only the agency `owner_id` may launch a paid subscription flow.
 *   B3 — `enhanceConditions` AI endpoint is gated to AGENT/ADMIN only.
 *   B5 — `EnsureTokenMatchesRole` falls back to the User model role when
 *        the request is authenticated via a TransientToken (Clerk session)
 *        instead of a Sanctum PAT.
 */
it('B1: forbids non-owner agency members from inviting new members', function (): void {
    $owner = User::factory()->agents()->create();
    $agency = Agency::factory()->create(['owner_id' => $owner->id]);
    $owner->forceFill(['agency_id' => $agency->id])->save();

    $member = User::factory()->agents()->create(['agency_id' => $agency->id]);

    $this->actingAs($member, 'sanctum')
        ->postJson('/api/v1/my/team/invite', [
            'email' => 'newhire@example.com',
            'role' => 'viewer',
        ])
        ->assertStatus(403)
        ->assertJsonFragment(['message' => 'Seul le propriétaire de l\'agence peut inviter de nouveaux membres.']);
});

it('B1: allows the agency owner to invite new members', function (): void {
    $owner = User::factory()->agents()->create();
    $agency = Agency::factory()->create(['owner_id' => $owner->id]);
    $owner->forceFill(['agency_id' => $agency->id])->save();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/my/team/invite', [
            'email' => 'newhire@example.com',
            'role' => 'viewer',
        ])
        ->assertStatus(201);
});

it('B2: forbids non-owner agency members from launching a subscription payment', function (): void {
    $owner = User::factory()->agents()->create();
    $agency = Agency::factory()->create(['owner_id' => $owner->id]);
    $owner->forceFill(['agency_id' => $agency->id])->save();

    $member = User::factory()->agents()->create(['agency_id' => $agency->id]);

    $plan = SubscriptionPlan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-b2',
        'description' => 'Test',
        'price' => 10_000,
        'price_yearly' => 100_000,
        'duration_days' => 30,
        'boost_score' => 10,
        'boost_duration_days' => 7,
        'features' => [],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($member, 'sanctum')
        ->postJson('/api/v1/subscriptions/subscribe', [
            'plan_id' => $plan->id,
            'billing_period' => 'monthly',
        ])
        ->assertStatus(403)
        ->assertJsonFragment(['message' => 'Seul le propriétaire de l\'agence peut souscrire un abonnement.']);
});

it('B3: forbids customer accounts from calling the AI lease conditions enhancer', function (): void {
    $customer = User::factory()->customers()->create();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/my/lease-contracts/ai/enhance-conditions', [
            'conditions' => 'Le locataire devra payer le loyer.',
        ])
        // owner.role middleware rejects with 403 before hitting controller.
        ->assertStatus(403);
});

it('B5: middleware rejects a non-PAT user whose role does not match (TransientToken fallback)', function (): void {
    // Unit-style test: invoke the middleware directly with a fake Request whose
    // `user()` returns a customer but `currentAccessToken()` returns a
    // TransientToken (not a PAT). This exercises the User-role fallback added
    // in the audit — every route that uses `token.role:` is currently also
    // gated by `owner.role`, so a feature test on real routes can’t reach this
    // code path.
    $customer = User::factory()->customers()->create();
    $customer->setRelation('currentAccessToken', new TransientToken);

    $request = Request::create('/api/v1/test', 'POST');
    $request->setUserResolver(fn () => $customer);

    /** @var EnsureTokenMatchesRole $middleware */
    $middleware = app(EnsureTokenMatchesRole::class);
    $response = $middleware->handle($request, fn ($r) => response('ok', 200), 'agent');

    expect($response->getStatusCode())->toBe(403);
    expect((string) $response->getContent())->toContain('USER_ROLE_MISMATCH');
});

it('B5: middleware rejects admin via User-role fallback on owner token routes', function (): void {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $admin->setRelation('currentAccessToken', new TransientToken);

    $request = Request::create('/api/v1/test', 'POST');
    $request->setUserResolver(fn () => $admin);

    $middleware = app(EnsureTokenMatchesRole::class);
    $response = $middleware->handle($request, fn ($r) => response('ok', 200), 'agent');

    expect($response->getStatusCode())->toBe(403);
    expect((string) $response->getContent())->toContain('panneau administrateur');
});

it('B5: middleware allows matching role via User-role fallback', function (): void {
    $agent = User::factory()->agents()->create();
    $agent->setRelation('currentAccessToken', new TransientToken);

    $request = Request::create('/api/v1/test', 'POST');
    $request->setUserResolver(fn () => $agent);

    $middleware = app(EnsureTokenMatchesRole::class);
    $response = $middleware->handle($request, fn ($r) => response('ok', 200), 'agent');

    expect($response->getStatusCode())->toBe(200);
});
