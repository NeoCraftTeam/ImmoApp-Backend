<?php

declare(strict_types=1);

use App\Exceptions\RoleContextMismatchException;
use App\Models\User;
use App\Services\LoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues a client-scoped token for a customer in client context', function (): void {
    $user = User::factory()->customers()->create();

    $token = app(LoginService::class)->issueApiTokenForLoginContext($user, 'client');

    expect($token->accessToken->name)->toStartWith('client_token_');
    expect($token->accessToken->abilities)->toContain('role:customer');
    expect($token->accessToken->abilities)->toContain('api:access');
});

it('issues an owner-scoped token for an agent in owner context', function (): void {
    $user = User::factory()->agents()->create();

    $token = app(LoginService::class)->issueApiTokenForLoginContext($user, 'owner');

    expect($token->accessToken->name)->toStartWith('owner_token_');
    expect($token->accessToken->abilities)->toContain('role:agent');
});

it('rejects admin in owner context', function (): void {
    $user = User::factory()->admin()->create();

    app(LoginService::class)->issueApiTokenForLoginContext($user, 'owner');
})->throws(RoleContextMismatchException::class, 'Utilisez le panneau administrateur.');

it('issues a client-scoped token for admin in client context', function (): void {
    $user = User::factory()->admin()->create();

    $token = app(LoginService::class)->issueApiTokenForLoginContext($user, 'client');

    expect($token->accessToken->name)->toStartWith('client_token_');
});

it('rejects customer in owner context', function (): void {
    $user = User::factory()->customers()->create();

    app(LoginService::class)->issueApiTokenForLoginContext($user, 'owner');
})->throws(RoleContextMismatchException::class, 'Accès réservé aux propriétaires et agences.');

it('rejects agent in client context', function (): void {
    $user = User::factory()->agents()->create();

    app(LoginService::class)->issueApiTokenForLoginContext($user, 'client');
})->throws(RoleContextMismatchException::class, 'Accès réservé aux clients. Utilisez le panneau propriétaire.');

it('revokes prior tokens in the same context prefix when issuing again', function (): void {
    $user = User::factory()->customers()->create();
    $loginService = app(LoginService::class);

    $first = $loginService->issueApiTokenForLoginContext($user, 'client');
    $second = $loginService->issueApiTokenForLoginContext($user, 'client');

    expect($user->tokens()->where('id', $first->accessToken->id)->exists())->toBeFalse();
    expect($user->tokens()->where('id', $second->accessToken->id)->exists())->toBeTrue();
});
