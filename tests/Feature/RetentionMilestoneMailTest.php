<?php

declare(strict_types=1);

use App\Actions\UnlockAd;
use App\Enums\AdStatus;
use App\Mail\AdApprovedMail;
use App\Mail\FirstAdUnlockCongratulationsMail;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('queues owner congratulations email when ad is approved', function (): void {
    Mail::fake();

    $owner = User::factory()->agents()->create([
        'email' => 'bailleur@example.com',
    ]);
    $ad = Ad::factory()->for($owner)->create([
        'status' => AdStatus::PENDING,
    ]);

    $ad->transitionTo(AdStatus::AVAILABLE);

    Mail::assertQueued(AdApprovedMail::class, fn (AdApprovedMail $mail): bool => $mail->ad->id === $ad->id);
});

it('queues first unlock congratulations email only once for customer', function (): void {
    Mail::fake();

    $landlord = User::factory()->agents()->create();
    $customer = User::factory()->customers()->create([
        'email' => 'locataire@example.com',
        'point_balance' => 100,
    ]);
    $ad1 = Ad::factory()->for($landlord)->create(['status' => AdStatus::AVAILABLE]);
    $ad2 = Ad::factory()->for($landlord)->create(['status' => AdStatus::AVAILABLE]);

    $action = app(UnlockAd::class);

    expect($action->execute($customer, $ad1, 2)['status'])->toBe('unlocked');
    expect($action->execute($customer, $ad2, 2)['status'])->toBe('unlocked');

    Mail::assertQueued(FirstAdUnlockCongratulationsMail::class, 1);
});

it('does not queue first unlock email for clerk placeholder addresses', function (): void {
    Mail::fake();

    $landlord = User::factory()->agents()->create();
    $customer = User::factory()->customers()->create([
        'email' => 'noreply@clerk.local',
        'point_balance' => 100,
    ]);
    $ad = Ad::factory()->for($landlord)->create(['status' => AdStatus::AVAILABLE]);

    expect(app(UnlockAd::class)->execute($customer, $ad, 2)['status'])->toBe('unlocked');

    Mail::assertNothingQueued();
});

it('does not queue first unlock congratulations when user is the ad owner', function (): void {
    Mail::fake();

    $owner = User::factory()->agents()->create([
        'email' => 'self@example.com',
        'point_balance' => 100,
    ]);
    $ad = Ad::factory()->for($owner)->create(['status' => AdStatus::AVAILABLE]);

    expect(app(UnlockAd::class)->execute($owner, $ad, 2)['status'])->toBe('owner');

    Mail::assertNothingQueued();
});
