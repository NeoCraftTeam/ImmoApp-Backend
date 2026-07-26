<?php

declare(strict_types=1);

use App\Enums\PointTransactionType;
use App\Events\Credits\CreditsBalanceUpdated;
use App\Models\User;
use App\Services\Monetization\PointService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('broadcasts CreditsBalanceUpdated with the new balance when crediting', function (): void {
    // Fake APRÈS création : la création d'un user peut créditer un bonus de
    // bienvenue (qui diffuse aussi) — on ne veut que l'event de notre crédit.
    $user = User::factory()->create();
    $start = (int) $user->fresh()->point_balance;
    Event::fake([CreditsBalanceUpdated::class]);

    app(PointService::class)->credit(
        $user,
        5,
        PointTransactionType::PURCHASE,
        'Achat pack test',
    );

    Event::assertDispatched(
        CreditsBalanceUpdated::class,
        fn (CreditsBalanceUpdated $e): bool => $e instanceof ShouldBroadcastNow
            && $e->userId === (string) $user->id
            && $e->balance === $start + 5
            && $e->transaction->points === 5,
    );
});

it('broadcasts CreditsBalanceUpdated when deducting points', function (): void {
    $user = User::factory()->create(['point_balance' => 10]);
    $start = (int) $user->fresh()->point_balance;
    Event::fake([CreditsBalanceUpdated::class]);

    app(PointService::class)->deduct($user, 3, 'Déverrouillage annonce');

    Event::assertDispatched(
        CreditsBalanceUpdated::class,
        fn (CreditsBalanceUpdated $e): bool => $e->balance === $start - 3
            && $e->transaction->points === -3,
    );
});

it('broadcasts on the user private channel', function (): void {
    $user = User::factory()->create(['point_balance' => 0]);
    $tx = app(PointService::class)->credit($user, 8, PointTransactionType::PURCHASE, 'x');

    $event = new CreditsBalanceUpdated((string) $user->id, 8, $tx);
    $channels = $event->broadcastOn();

    expect($channels[0]->name)->toBe("private-user.{$user->id}")
        ->and($event->broadcastAs())->toBe('credits.updated')
        ->and($event->broadcastWith()['balance'])->toBe(8)
        ->and($event->broadcastWith()['transaction']['points'])->toBe(8);
});
