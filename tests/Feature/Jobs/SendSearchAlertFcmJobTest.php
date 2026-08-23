<?php

declare(strict_types=1);

use App\Contracts\FirebaseMessagingResolverInterface;
use App\Jobs\SendSearchAlertFcmJob;
use App\Models\Ad;
use App\Models\FcmToken;
use App\Models\User;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;

/**
 * Bind a mocked resolver returning the given (mocked or null) Messaging client,
 * mirroring the container singleton the queue worker injects into handle().
 */
function bindFcmResolver(?Messaging $messaging): void
{
    $resolver = Mockery::mock(FirebaseMessagingResolverInterface::class);
    $resolver->shouldReceive('make')->andReturn($messaging);
    app()->instance(FirebaseMessagingResolverInterface::class, $resolver);
}

it('sends a push through the injected messaging client for each registered token', function (): void {
    $recipient = User::factory()->create();
    $ad = Ad::factory()->create();

    FcmToken::create(['user_id' => $recipient->id, 'token' => 'tok-a', 'platform' => 'web']);
    FcmToken::create(['user_id' => $recipient->id, 'token' => 'tok-b', 'platform' => 'android']);

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('send')
        ->twice()
        ->with(Mockery::type(CloudMessage::class));
    bindFcmResolver($messaging);

    app()->call([new SendSearchAlertFcmJob($recipient->id, $ad->id), 'handle']);

    expect(FcmToken::where('user_id', $recipient->id)->count())->toBe(2);
});

it('prunes tokens the messaging client reports as unknown', function (): void {
    $recipient = User::factory()->create();
    $ad = Ad::factory()->create();

    FcmToken::create(['user_id' => $recipient->id, 'token' => 'tok-dead', 'platform' => 'web']);

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('send')
        ->once()
        ->andThrow(NotFound::becauseTokenNotFound('tok-dead'));
    bindFcmResolver($messaging);

    app()->call([new SendSearchAlertFcmJob($recipient->id, $ad->id), 'handle']);

    expect(FcmToken::where('user_id', $recipient->id)->exists())->toBeFalse();
});

it('skips gracefully without sending when the resolver has no credentials', function (): void {
    $recipient = User::factory()->create();
    $ad = Ad::factory()->create();

    FcmToken::create(['user_id' => $recipient->id, 'token' => 'tok-a', 'platform' => 'web']);

    // A null client is the graceful "credentials unavailable" signal: the job
    // must return without touching Messaging and without pruning the token.
    bindFcmResolver(null);

    app()->call([new SendSearchAlertFcmJob($recipient->id, $ad->id), 'handle']);

    expect(FcmToken::where('user_id', $recipient->id)->count())->toBe(1);
});
