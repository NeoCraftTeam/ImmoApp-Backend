<?php

declare(strict_types=1);

use App\Enums\AdStatus;
use App\Events\AdCreated;
use App\Listeners\NotifyAdminsOfPendingAd;
use App\Mail\AdSubmissionConfirmationMail;
use App\Mail\FirstAdCelebrationMail;
use App\Mail\GdprDataExportMail;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

// ── FirstAdCelebrationMail — listener behaviour ────────────────────────────────

it('sends FirstAdCelebrationMail when owner posts their very first pending ad', function (): void {
    Mail::fake();

    $owner = User::factory()->agents()->create();
    $ad = Ad::withoutEvents(fn () => Ad::factory()->for($owner)->create([
        'status' => AdStatus::PENDING,
    ]));

    app(NotifyAdminsOfPendingAd::class)->handle(new AdCreated($ad));

    Mail::assertQueued(FirstAdCelebrationMail::class, fn ($m) => $m->hasTo($owner->email));
    Mail::assertQueued(AdSubmissionConfirmationMail::class, fn ($m) => $m->hasTo($owner->email));
});

it('does not send FirstAdCelebrationMail when owner already has other ads', function (): void {
    Mail::fake();

    $owner = User::factory()->agents()->create();

    Ad::withoutEvents(fn () => Ad::factory()->for($owner)->create([
        'status' => AdStatus::AVAILABLE,
    ]));

    $secondAd = Ad::withoutEvents(fn () => Ad::factory()->for($owner)->create([
        'status' => AdStatus::PENDING,
    ]));

    app(NotifyAdminsOfPendingAd::class)->handle(new AdCreated($secondAd));

    Mail::assertNotQueued(FirstAdCelebrationMail::class);
    Mail::assertQueued(AdSubmissionConfirmationMail::class, fn ($m) => $m->hasTo($owner->email));
});

it('does not send FirstAdCelebrationMail when ad status is not pending on creation', function (): void {
    Mail::fake();

    $owner = User::factory()->agents()->create();
    $ad = Ad::withoutEvents(fn () => Ad::factory()->for($owner)->create([
        'status' => AdStatus::AVAILABLE,
    ]));

    app(NotifyAdminsOfPendingAd::class)->handle(new AdCreated($ad));

    Mail::assertNotQueued(FirstAdCelebrationMail::class);
    Mail::assertNotQueued(AdSubmissionConfirmationMail::class);
});

// ── GdprDataExportMail — content & attachment ─────────────────────────────────

it('GdprDataExportMail builds payload and attaches json file', function (): void {
    $user = User::factory()->agents()->create();

    $mail = new GdprDataExportMail($user);
    $attachments = $mail->attachments();

    expect($attachments)->toHaveCount(1);

    $filename = $attachments[0]->as;
    expect($filename)->toStartWith('keyhome-mes-donnees-');
    expect($filename)->toEndWith('.json');
});

it('GdprDataExportMail has correct subject and recipient', function (): void {
    Mail::fake();

    $user = User::factory()->create();

    Mail::to($user->email)->send(new GdprDataExportMail($user));

    Mail::assertQueued(GdprDataExportMail::class, fn ($mail) => $mail->hasTo($user->email)
        && str_contains((string) $mail->envelope()->subject, 'RGPD'));
});
