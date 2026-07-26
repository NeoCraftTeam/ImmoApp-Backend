<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| OAuth Authentication Tests
|--------------------------------------------------------------------------
*/

describe('OAuth Provider Validation', function (): void {
    it('rejects route for unsupported OAuth providers', function (): void {
        // Route constraint only allows google|facebook|apple, so unsupported returns 404
        $response = $this->postJson('/api/v1/auth/oauth/twitter', [
            'token' => 'fake-token',
        ]);

        $response->assertNotFound();
    });

    it('requires token for OAuth authentication', function (): void {
        $response = $this->postJson('/api/v1/auth/oauth/google', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    });
});

describe('Google OAuth Authentication', function (): void {
    it('authenticates existing user with Google', function (): void {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'google_id' => 'google-123',
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('test@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Test User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->with('valid-google-token')
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/v1/auth/oauth/google', [
            'token' => 'valid-google-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user',
                'token',
                'is_new_user',
            ])
            ->assertJson([
                'message' => 'Connexion réussie',
                'is_new_user' => false,
            ]);
    });

    it('issues role-sealed tokens (never wildcard) for OAuth logins', function (): void {
        $user = User::factory()->create([
            'email' => 'sealed@example.com',
            'google_id' => 'google-sealed-1',
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-sealed-1');
        $socialiteUser->shouldReceive('getEmail')->andReturn('sealed@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Sealed User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn(null);

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->with('valid-google-token')
            ->andReturn($socialiteUser);

        $this->postJson('/api/v1/auth/oauth/google', ['token' => 'valid-google-token'])
            ->assertOk();

        $accessToken = $user->tokens()->latest('id')->first();

        expect($accessToken)->not->toBeNull()
            ->and($accessToken->abilities)->toContain('role:'.$user->role->value)
            ->and($accessToken->abilities)->toContain('api:access')
            ->and($accessToken->abilities)->not->toContain('*')
            ->and($accessToken->name)->toStartWith($user->sanctumSessionPrefix().'_oauth_google_')
            ->and($accessToken->family_id)->not->toBeNull()
            ->and($accessToken->expires_at)->not->toBeNull();
    });

    it('creates new user with Google OAuth', function (): void {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-new-456');
        $socialiteUser->shouldReceive('getEmail')->andReturn('newuser@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('New User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
        $socialiteUser->shouldReceive('getRaw')->andReturn([
            'given_name' => 'New',
            'family_name' => 'User',
        ]);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->with('valid-google-token')
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/v1/auth/oauth/google', [
            'token' => 'valid-google-token',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Compte créé avec succès',
                'is_new_user' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'google_id' => 'google-new-456',
            'oauth_provider' => 'google',
            'role' => UserRole::CUSTOMER->value,
        ]);
    });

    it('creates an agent account via OAuth when role=agent is requested', function (): void {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-agent-789');
        $socialiteUser->shouldReceive('getEmail')->andReturn('agent@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Agent User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
        $socialiteUser->shouldReceive('getRaw')->andReturn([]);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->andReturn($socialiteUser);

        // Owner panel / owners mobile app pass role=agent — the account is
        // created as agent (type individual, agency linking via onboarding)
        $response = $this->postJson('/api/v1/auth/oauth/google', [
            'token' => 'valid-google-token',
            'role' => 'agent',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'agent@example.com',
            'role' => UserRole::AGENT->value,
            'type' => 'individual',
        ]);
    });

    it('rejects a non-whitelisted OAuth role with a validation error', function (): void {
        $response = $this->postJson('/api/v1/auth/oauth/google', [
            'token' => 'valid-google-token',
            'role' => 'admin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', [
            'email' => 'wannabe-admin@example.com',
        ]);
    });

    it('never changes the role of an existing account whatever role is requested', function (): void {
        User::factory()->create([
            'email' => 'customer@example.com',
            'google_id' => 'google-existing-111',
            'role' => UserRole::CUSTOMER,
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-existing-111');
        $socialiteUser->shouldReceive('getEmail')->andReturn('customer@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Existing Customer');
        $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
        $socialiteUser->shouldReceive('getRaw')->andReturn([]);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/v1/auth/oauth/google', [
            'token' => 'valid-google-token',
            'role' => 'agent',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'role' => UserRole::CUSTOMER->value,
        ]);
    });

    it('links Google to existing email account requires confirmation', function (): void {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => null,
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-link-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('existing@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Existing User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/v1/auth/oauth/google', [
            'token' => 'valid-token',
        ]);

        // Security fix (P4-33): auto-linking is disabled — user must confirm in a separate step
        $response->assertOk()
            ->assertJsonFragment(['requires_link_confirmation' => true])
            ->assertJsonStructure(['linking_token', 'message']);

        // Google ID should NOT be linked yet — pending confirmation
        $existingUser->refresh();
        expect($existingUser->google_id)->toBeNull();
        expect($existingUser->pending_oauth_provider)->toBe('google');
        expect($existingUser->pending_oauth_token)->not->toBeNull();
    });
});

describe('Facebook OAuth Authentication', function (): void {
    it('authenticates user with Facebook', function (): void {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('fb-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('fbuser@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('FB User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://facebook.com/avatar.jpg');
        $socialiteUser->shouldReceive('getRaw')->andReturn([
            'first_name' => 'FB',
            'last_name' => 'User',
        ]);

        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/v1/auth/oauth/facebook', [
            'token' => 'valid-fb-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user', 'token', 'is_new_user']);

        $this->assertDatabaseHas('users', [
            'email' => 'fbuser@example.com',
            'facebook_id' => 'fb-123',
        ]);
    });

    it('authenticates user with GitHub', function (): void {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('gh-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('ghuser@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('GH User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://github.com/avatar.jpg');
        $socialiteUser->shouldReceive('getRaw')->andReturn([]);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/v1/auth/oauth/github', [
            'token' => 'valid-gh-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user', 'token', 'is_new_user']);

        $this->assertDatabaseHas('users', [
            'email' => 'ghuser@example.com',
            'github_id' => 'gh-123',
        ]);
    });
});

describe('Apple OAuth Authentication', function (): void {
    it('authenticates user with Apple using id_token', function (): void {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('apple-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('appleuser@privaterelay.appleid.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Apple User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
        $socialiteUser->shouldReceive('getRaw')->andReturn([]);

        Socialite::shouldReceive('driver')
            ->with('apple')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->with('valid-apple-id-token')
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/v1/auth/oauth/apple', [
            'token' => 'not-used',
            'id_token' => 'valid-apple-id-token',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'apple_id' => 'apple-123',
        ]);
    });
});

describe('OAuth Provider Link/Unlink', function (): void {
    it('links OAuth provider to authenticated user', function (): void {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'google_id' => null,
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-link-456');
        $socialiteUser->shouldReceive('getEmail')->andReturn($user->email);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->andReturn($socialiteUser);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/oauth/google/link', [
                'token' => 'valid-token',
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Compte google lié avec succès']);

        $user->refresh();
        expect($user->google_id)->toBe('google-link-456');
    });

    it('prevents linking provider already linked to another account', function (): void {
        $otherUser = User::factory()->create([
            'google_id' => 'google-existing-789',
        ]);

        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-existing-789');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->andReturn($socialiteUser);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/oauth/google/link', [
                'token' => 'valid-token',
            ]);

        $response->assertStatus(409)
            ->assertJson(['message' => 'Ce compte google est déjà lié à un autre utilisateur']);
    });

    it('unlinks OAuth provider from user with password', function (): void {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'google_id' => 'google-to-unlink',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/v1/auth/oauth/google/unlink');

        $response->assertOk()
            ->assertJson(['message' => 'Compte google délié avec succès']);

        $user->refresh();
        expect($user->google_id)->toBeNull();
    });

    it('prevents unlinking only auth method', function (): void {
        $user = User::factory()->create([
            'password' => null,
            'google_id' => 'only-auth-method',
            'facebook_id' => null,
            'apple_id' => null,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/v1/auth/oauth/google/unlink');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Impossible de délier ce compte. Définissez d\'abord un mot de passe ou liez un autre provider.',
            ]);
    });

    it('allows unlinking when another provider is linked', function (): void {
        $user = User::factory()->create([
            'password' => null,
            'google_id' => 'google-auth',
            'facebook_id' => 'facebook-auth',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/v1/auth/oauth/google/unlink');

        $response->assertOk();

        $user->refresh();
        expect($user->google_id)->toBeNull();
        expect($user->facebook_id)->toBe('facebook-auth');
    });
});

describe('OAuth Redirect Flow (Web)', function (): void {
    beforeEach(function (): void {
        Config::set('services.google.client_id', 'test-google-client-id-for-redirect');
    });

    it('returns redirect URL for OAuth provider', function (): void {
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('stateless')
            ->andReturnSelf();
        Socialite::shouldReceive('with')
            ->andReturnSelf();
        Socialite::shouldReceive('redirect')
            ->andReturnSelf();
        Socialite::shouldReceive('getTargetUrl')
            ->andReturn('https://accounts.google.com/oauth/authorize?...');

        $response = $this->getJson('/api/v1/auth/oauth/google/redirect');

        $response->assertOk()
            ->assertJsonStructure(['redirect_url']);
    });

    it('accepts keyhome mobile deep-link redirect_uri for visitors app', function (): void {
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('stateless')
            ->andReturnSelf();
        Socialite::shouldReceive('with')
            ->withArgs(function (array $args): bool {
                $state = json_decode(base64_decode((string) ($args['state'] ?? '')), true);

                return is_array($state)
                    && ($state['redirect_uri'] ?? '') === 'keyhome://auth/callback';
            })
            ->andReturnSelf();
        Socialite::shouldReceive('redirect')
            ->andReturnSelf();
        Socialite::shouldReceive('getTargetUrl')
            ->andReturn('https://accounts.google.com/oauth/authorize?...');

        $response = $this->getJson(
            '/api/v1/auth/oauth/google/redirect?redirect_uri='.urlencode('keyhome://auth/callback'),
            ['X-KeyHome-Client' => 'keyhome-mobile-visitors'],
        );

        $response->assertOk()->assertJsonStructure(['redirect_url']);
    });

    it('accepts exp redirect_uri for Expo Go during development', function (): void {
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('stateless')
            ->andReturnSelf();
        Socialite::shouldReceive('with')
            ->withArgs(function (array $args): bool {
                $state = json_decode(base64_decode((string) ($args['state'] ?? '')), true);

                return is_array($state)
                    && ($state['redirect_uri'] ?? '') === 'exp://127.0.0.1:8081/--/auth/callback';
            })
            ->andReturnSelf();
        Socialite::shouldReceive('redirect')
            ->andReturnSelf();
        Socialite::shouldReceive('getTargetUrl')
            ->andReturn('https://accounts.google.com/oauth/authorize?...');

        $response = $this->getJson(
            '/api/v1/auth/oauth/google/redirect?redirect_uri='.urlencode('exp://127.0.0.1:8081/--/auth/callback'),
            ['X-KeyHome-Client' => 'keyhome-mobile-visitors'],
        );

        $response->assertOk()->assertJsonStructure(['redirect_url']);
    });
});

describe('OAuth Error Handling', function (): void {
    it('handles invalid OAuth token gracefully', function (): void {
        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->andThrow(new Exception('Invalid token'));

        $response = $this->postJson('/api/v1/auth/oauth/google', [
            'token' => 'invalid-token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Échec de l\'authentification. Veuillez réessayer.']);
    });

    it('handles missing email from OAuth provider', function (): void {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('no-email-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn(null);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/v1/auth/oauth/google', [
            'token' => 'valid-token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Impossible de récupérer les informations utilisateur depuis google',
            ]);
    });
});

describe('OAuth Rate Limiting', function (): void {
    it('rate limits OAuth authentication attempts', function (): void {
        Socialite::shouldReceive('driver')->andReturnSelf();
        Socialite::shouldReceive('userFromToken')
            ->andThrow(new Exception('Invalid token'));

        // Make 10 requests (limit)
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/oauth/google', ['token' => 'fake']);
        }

        // 11th request should be rate limited
        $response = $this->postJson('/api/v1/auth/oauth/google', [
            'token' => 'fake-token',
        ]);

        $response->assertStatus(429);
    });
});
