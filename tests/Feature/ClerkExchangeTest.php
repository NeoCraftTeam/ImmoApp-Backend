<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\ClerkJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

/*
|--------------------------------------------------------------------------
| Clerk Exchange Endpoint Tests
|--------------------------------------------------------------------------
| POST /api/v1/auth/clerk/exchange
*/

/**
 * Build a fake Clerk user payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fakeClerkPayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'clerk_abc123',
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'image_url' => 'https://img.clerk.dev/avatar.jpg',
        'primary_email_address_id' => 'iea_1',
        'email_addresses' => [
            [
                'id' => 'iea_1',
                'email_address' => 'jean@example.com',
                'verification' => ['status' => 'verified'],
            ],
        ],
        'external_accounts' => [
            ['provider' => 'oauth_google', 'verification' => ['status' => 'verified']],
        ],
    ], $overrides);
}

describe('Clerk Exchange – authentication flows', function (): void {
    it('authenticates a user matched by clerk_id', function (): void {
        $user = User::factory()->create([
            'clerk_id' => 'clerk_abc123',
            'email' => 'jean@example.com',
        ]);

        $this->mock(ClerkJwtService::class)
            ->shouldReceive('verifyAndFetchUser')
            ->once()
            ->andReturn(fakeClerkPayload());

        $response = $this->withToken('fake-clerk-jwt')
            ->postJson('/api/v1/auth/clerk/exchange');

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'user', 'panel_sso_url']);

        expect($user->fresh()->clerk_id)->toBe('clerk_abc123');
    });

    it('authenticates and links a user matched by email when clerk_id is null', function (): void {
        $user = User::factory()->create([
            'clerk_id' => null,
            'email' => 'jean@example.com',
        ]);

        $this->mock(ClerkJwtService::class)
            ->shouldReceive('verifyAndFetchUser')
            ->once()
            ->andReturn(fakeClerkPayload(['id' => 'clerk_new_789']));

        $response = $this->withToken('fake-clerk-jwt')
            ->postJson('/api/v1/auth/clerk/exchange');

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'user', 'panel_sso_url']);

        expect($user->fresh()->clerk_id)->toBe('clerk_new_789');
    });

    it('authenticates cross-provider: user has different clerk_id but same email', function (): void {
        // User previously linked via Facebook (has a different clerk_id)
        $user = User::factory()->create([
            'clerk_id' => 'clerk_facebook_old',
            'email' => 'jean@example.com',
        ]);

        // Now they sign in via Google which generates a NEW clerk_id
        $this->mock(ClerkJwtService::class)
            ->shouldReceive('verifyAndFetchUser')
            ->once()
            ->andReturn(fakeClerkPayload(['id' => 'clerk_google_new']));

        $response = $this->withToken('fake-clerk-jwt')
            ->postJson('/api/v1/auth/clerk/exchange');

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'user', 'panel_sso_url']);

        // clerk_id should be updated to the new Google clerk_id
        expect($user->fresh()->clerk_id)->toBe('clerk_google_new');
    });

    it('creates a new OAuth user immediately without sending an OTP email', function (): void {
        $this->mock(ClerkJwtService::class)
            ->shouldReceive('verifyAndFetchUser')
            ->once()
            ->andReturn(fakeClerkPayload([
                'id' => 'clerk_brand_new',
                'email_addresses' => [
                    [
                        'id' => 'iea_1',
                        'email_address' => 'newuser@example.com',
                        'verification' => ['status' => 'verified'],
                    ],
                ],
                'primary_email_address_id' => 'iea_1',
                'external_accounts' => [
                    ['provider' => 'oauth_google', 'verification' => ['status' => 'verified']],
                ],
            ]));

        $response = $this->withToken('fake-clerk-jwt')
            ->postJson('/api/v1/auth/clerk/exchange');

        $response->assertCreated()
            ->assertJsonStructure(['access_token', 'user', 'panel_sso_url']);

        Mail::assertNothingSent();

        $this->assertDatabaseHas('users', [
            'clerk_id' => 'clerk_brand_new',
            'email' => 'newuser@example.com',
        ]);

        expect(User::query()->where('email', 'newuser@example.com')->first()?->email_verified_at)->not->toBeNull();
    });

    it('creates an agent account when registration_intent is agent', function (): void {
        $this->mock(ClerkJwtService::class)
            ->shouldReceive('verifyAndFetchUser')
            ->once()
            ->andReturn(fakeClerkPayload([
                'id' => 'clerk_owner_new',
                'email_addresses' => [
                    [
                        'id' => 'iea_1',
                        'email_address' => 'owner-new@example.com',
                        'verification' => ['status' => 'verified'],
                    ],
                ],
            ]));

        $response = $this->withToken('fake-clerk-jwt')
            ->postJson('/api/v1/auth/clerk/exchange', [
                'registration_intent' => 'agent',
                'login_context' => 'owner',
            ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'agent');

        $this->assertDatabaseHas('users', [
            'email' => 'owner-new@example.com',
            'clerk_id' => 'clerk_owner_new',
        ]);
    });

    it('returns 401 when the Clerk token is invalid', function (): void {
        $this->mock(ClerkJwtService::class)
            ->shouldReceive('verifyAndFetchUser')
            ->once()
            ->andReturn(null);

        $response = $this->withToken('invalid-jwt')
            ->postJson('/api/v1/auth/clerk/exchange');

        $response->assertUnauthorized();
    });

    it('returns 403 when admin exchanges with login_context owner', function (): void {
        User::factory()->admin()->create([
            'clerk_id' => 'clerk_admin_owner',
            'email' => 'admin-owner@example.com',
        ]);

        $this->mock(ClerkJwtService::class)
            ->shouldReceive('verifyAndFetchUser')
            ->once()
            ->andReturn(fakeClerkPayload([
                'id' => 'clerk_admin_owner',
                'email_addresses' => [
                    ['id' => 'iea_1', 'email_address' => 'admin-owner@example.com'],
                ],
            ]));

        $response = $this->withToken('fake-clerk-jwt')
            ->postJson('/api/v1/auth/clerk/exchange', [
                'login_context' => 'owner',
            ]);

        $response->assertUnauthorized()
            ->assertJsonPath('code', 'PANEL_ACCESS_DENIED')
            ->assertJsonPath('message', 'Identifiants incorrects');
    });

    it('returns 403 with email hint when the Laravel account is not verified', function (): void {
        $user = User::factory()->unverified()->create([
            'clerk_id' => 'clerk_abc123',
            'email' => 'unverified@example.com',
        ]);

        $this->mock(ClerkJwtService::class)
            ->shouldReceive('verifyAndFetchUser')
            ->once()
            ->andReturn(fakeClerkPayload());

        $response = $this->withToken('fake-clerk-jwt')
            ->postJson('/api/v1/auth/clerk/exchange');

        $response->assertForbidden()
            ->assertJson([
                'email_verification_required' => true,
                'email' => $user->email,
            ]);
    });
});
