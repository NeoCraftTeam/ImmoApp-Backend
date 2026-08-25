<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| OAuth web callback + exchange-code flow
|--------------------------------------------------------------------------
| Characterization net locking the behaviour of SocialAuthController::callback()
| (both the login flow and the authenticated link flow) and exchangeToken(),
| which had no coverage before the SocialAuthService extraction. The callback
| resolves the social user through Socialite's stateless `->user()` seam (not
| `userFromToken`), so these tests mock that chain.
*/

it('callback login flow redirects with an exchange code that redeems to a token', function (): void {
    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('google-cb-login-1');
    $socialUser->shouldReceive('getEmail')->andReturn('cb-login@example.com');
    $socialUser->shouldReceive('getName')->andReturn('CB Login');
    $socialUser->shouldReceive('getAvatar')->andReturn(null);
    $socialUser->shouldReceive('getRaw')->andReturn([]);

    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('stateless')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($socialUser);

    $frontend = (string) config('app.frontend_url');
    $state = base64_encode((string) json_encode(['redirect_uri' => $frontend.'/auth/callback']));

    $response = $this->get('/api/v1/auth/oauth/google/callback?code=abc&state='.urlencode($state));

    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    expect($location)->toStartWith($frontend.'/auth/callback?exchange_code=');

    $this->assertDatabaseHas('users', [
        'email' => 'cb-login@example.com',
        'google_id' => 'google-cb-login-1',
    ]);

    // Redeem the single-use exchange code — this also pins exchangeToken().
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $exchange = $this->getJson('/api/v1/auth/oauth/exchange-token?exchange_code='.$query['exchange_code']);

    $exchange->assertOk()
        ->assertJsonStructure(['token', 'is_new_user'])
        ->assertJsonPath('is_new_user', true);

    // Codes are single-use: a second redemption is rejected.
    $this->getJson('/api/v1/auth/oauth/exchange-token?exchange_code='.$query['exchange_code'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'EXCHANGE_CODE_INVALID');
});

it('callback link flow links the provider to the account behind the link_code', function (): void {
    $user = User::factory()->create(['google_id' => null]);
    $linkCode = str_repeat('a', 64);
    Cache::put('oauth_link_'.$linkCode, (string) $user->id, now()->addMinutes(10));

    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getId')->andReturn('google-cb-link-1');

    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('stateless')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($socialUser);

    $frontend = (string) config('app.frontend_url');
    $state = base64_encode((string) json_encode([
        'redirect_uri' => $frontend.'/auth/callback',
        'link_code' => $linkCode,
    ]));

    $response = $this->get('/api/v1/auth/oauth/google/callback?code=abc&state='.urlencode($state));

    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    expect($location)->toContain('linked=1')
        ->and($location)->toContain('provider=google');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'google_id' => 'google-cb-link-1',
    ]);

    // Link code is consumed (single-use).
    expect(Cache::get('oauth_link_'.$linkCode))->toBeNull();
});

it('exchange-token rejects an unknown code', function (): void {
    $this->getJson('/api/v1/auth/oauth/exchange-token?exchange_code='.str_repeat('z', 64))
        ->assertStatus(422)
        ->assertJsonPath('code', 'EXCHANGE_CODE_INVALID');
});
