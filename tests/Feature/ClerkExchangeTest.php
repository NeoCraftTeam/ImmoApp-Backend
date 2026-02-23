<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\ClerkJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
            ['id' => 'iea_1', 'email_address' => 'jean@example.com'],
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

    it('returns otp_required for a brand-new email not in the database', function (): void {
        $this->mock(ClerkJwtService::class)
            ->shouldReceive('verifyAndFetchUser')
            ->once()
            ->andReturn(fakeClerkPayload([
                'id' => 'clerk_brand_new',
                'email_addresses' => [
                    ['id' => 'iea_1', 'email_address' => 'newuser@example.com'],
                ],
                'primary_email_address_id' => 'iea_1',
            ]));

        $response = $this->withToken('fake-clerk-jwt')
            ->postJson('/api/v1/auth/clerk/exchange');

        $response->assertOk()
            ->assertJsonStructure(['state', 'email_hint'])
            ->assertJson(['state' => 'otp_required']);
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
});
