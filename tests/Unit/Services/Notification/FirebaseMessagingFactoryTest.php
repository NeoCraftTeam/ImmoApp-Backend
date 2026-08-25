<?php

declare(strict_types=1);

use App\Contracts\FirebaseMessagingResolverInterface;
use App\Services\Notification\FirebaseMessagingFactory;
use Illuminate\Support\Facades\Log;

it('binds the Firebase messaging resolver as a shared singleton', function (): void {
    expect(app(FirebaseMessagingResolverInterface::class))
        ->toBeInstanceOf(FirebaseMessagingFactory::class)
        ->toBe(app(FirebaseMessagingResolverInterface::class));
});

it('returns null and warns only once when credentials are absent', function (): void {
    // An empty config path short-circuits resolution to a guaranteed-missing
    // file: storage_path('../') is a directory (so the fallback is never
    // reached) and file_exists('') is false. This keeps the test deterministic
    // regardless of whether a real credentials file exists on the host.
    config(['chat.firebase.credentials' => '']);
    Log::spy();

    $factory = new FirebaseMessagingFactory;

    expect($factory->make())->toBeNull()
        ->and($factory->make())->toBeNull();

    // Logged once — not per call — proving the null resolution is memoised.
    Log::shouldHaveReceived('warning')
        ->with('[FCM] Firebase credentials not found. Skipping push notification.', Mockery::type('array'))
        ->once();
});
