<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Laravel\Pulse\Http\Middleware\Authorize;

/**
 * The Pulse dashboard exposes slow queries, exceptions and per-user activity.
 * It must sit behind the admin-only `viewPulse` gate. These tests assert both
 * halves of that control: the gate middleware is actually wired into Pulse's
 * route stack (config), and the middleware itself denies everyone but admins.
 */
it('wires the admin-only Authorize middleware into every Pulse route', function (): void {
    expect(config('pulse.middleware'))->toContain(Authorize::class);
});

it('blocks guests from the Pulse dashboard', function (): void {
    $middleware = app(Authorize::class);

    expect(fn (): mixed => $middleware->handle(
        Request::create('/pulse'),
        fn (): string => 'reached',
    ))->toThrow(AuthorizationException::class);
});

it('blocks authenticated non-admins from the Pulse dashboard', function (): void {
    $this->actingAs(User::factory()->customers()->create());

    $middleware = app(Authorize::class);

    expect(fn (): mixed => $middleware->handle(
        Request::create('/pulse'),
        fn (): string => 'reached',
    ))->toThrow(AuthorizationException::class);
});

it('allows admins through to the Pulse dashboard', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $middleware = app(Authorize::class);

    $response = $middleware->handle(
        Request::create('/pulse'),
        fn (): string => 'reached',
    );

    expect($response)->toBe('reached');
});
