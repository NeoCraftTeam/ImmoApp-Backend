<?php

declare(strict_types=1);

use App\Models\EmailPreference;
use App\Models\EmailSendLog;
use App\Models\User;
use App\Support\EngagementMailGuard;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->guard = new EngagementMailGuard;
    $this->user = User::factory()->create();
});

it('lets the first send of a mail through', function (): void {
    expect($this->guard->allows($this->user, 'welcome_drip_1'))->toBeTrue();
});

it('honours an opt-out on the category it was asked about, and only that one', function (): void {
    EmailPreference::getOrCreateForUser($this->user)->update(['engagement_emails' => false]);

    expect($this->guard->allows($this->user, 'welcome_drip_1'))->toBeFalse()
        // The digest sits in its own category; an engagement opt-out must not
        // silence mail the reader explicitly subscribed to.
        ->and($this->guard->allows($this->user, 'weekly_digest', 'digest_emails'))->toBeTrue();
});

it('holds a mail back while its cooldown runs', function (): void {
    logSentAt($this->user, 'inactivity_7', now()->subDays(13));

    expect($this->guard->allows($this->user, 'inactivity_7'))->toBeFalse();
});

it('releases a mail once its cooldown has run out', function (): void {
    logSentAt($this->user, 'inactivity_7', now()->subDays(15));

    expect($this->guard->allows($this->user, 'inactivity_7'))->toBeTrue();
});

it('measures the cooldown the caller asked for, not the default', function (): void {
    // The owner activity report asks for 7 days, so a send 8 days back is spent
    // for it while still blocking anything on the 14-day default.
    logSentAt($this->user, 'owner_activity', now()->subDays(8));

    expect($this->guard->allows($this->user, 'owner_activity', cooldownDays: 7))->toBeTrue()
        ->and($this->guard->allows($this->user, 'owner_activity'))->toBeFalse();
});

it('scopes the cooldown to the mail key it was given', function (): void {
    logSentAt($this->user, 'welcome_drip_1', now()->subHour());

    expect($this->guard->allows($this->user, 'welcome_drip_1'))->toBeFalse()
        ->and($this->guard->allows($this->user, 'welcome_drip_3'))->toBeTrue();
});

it('stops at the weekly ceiling however varied the mails are', function (): void {
    foreach (['welcome_drip_1', 'abandoned_search', 'inactivity_7'] as $key) {
        logSentAt($this->user, $key, now()->subDay());
    }

    expect(EngagementMailGuard::MAX_PER_WEEK)->toBe(3)
        ->and($this->guard->allows($this->user, 'owner_activity'))->toBeFalse();
});

it('only counts the last seven days towards the ceiling', function (): void {
    foreach (['welcome_drip_1', 'abandoned_search', 'inactivity_7'] as $key) {
        logSentAt($this->user, $key, now()->subDays(8));
    }

    expect($this->guard->allows($this->user, 'owner_activity'))->toBeTrue();
});

it('counts the ceiling per user', function (): void {
    $other = User::factory()->create();

    foreach (['welcome_drip_1', 'abandoned_search', 'inactivity_7'] as $key) {
        logSentAt($this->user, $key, now()->subDay());
    }

    expect($this->guard->allows($other, 'welcome_drip_1'))->toBeTrue();
});

it('lets mail the reader must act on through a week that is already full', function (): void {
    foreach (['welcome_drip_1', 'abandoned_search', 'inactivity_7'] as $key) {
        logSentAt($this->user, $key, now()->subDay());
    }

    expect($this->guard->allows($this->user, 'failed_payment', cooldownDays: 3, respectWeeklyCap: false))->toBeTrue();
});

it('still applies the cooldown to mail that skips the ceiling', function (): void {
    // BUG CATCH: `respectWeeklyCap: false` returns early, so the cooldown has to
    // be checked before it — otherwise a declined card would mail every morning
    // until it was fixed.
    logSentAt($this->user, 'failed_payment', now()->subDay());

    expect($this->guard->allows($this->user, 'failed_payment', cooldownDays: 3, respectWeeklyCap: false))->toBeFalse();
});

it('still refuses mail that skips the ceiling to someone who opted the category out', function (): void {
    EmailPreference::getOrCreateForUser($this->user)->update(['digest_emails' => false]);

    expect($this->guard->allows($this->user, 'weekly_digest', 'digest_emails', respectWeeklyCap: false))->toBeFalse();
});

it('makes an unsolicited mail give way to the mail that skipped the ceiling', function (): void {
    logSentAt($this->user, 'weekly_digest', now()->subDay());
    logSentAt($this->user, 'failed_payment', now()->subDay());
    logSentAt($this->user, 'welcome_drip_1', now()->subDay());

    // The digest and the payment notice never asked the ceiling for permission,
    // but they still occupy their slots: the drip is what yields, not them.
    expect($this->guard->allows($this->user, 'inactivity_7'))->toBeFalse();
});

it('records a send as a row, so the ceiling survives a deploy', function (): void {
    $this->guard->record($this->user, 'abandoned_search');

    $log = EmailSendLog::query()->where('user_id', $this->user->id)->sole();

    expect($log->mail_key)->toBe('abandoned_search')
        ->and($log->sent_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('closes the door behind itself', function (): void {
    expect($this->guard->allows($this->user, 'abandoned_search'))->toBeTrue();

    $this->guard->record($this->user, 'abandoned_search');

    expect($this->guard->allows($this->user, 'abandoned_search'))->toBeFalse();
});

it('prunes history older than a quarter and keeps the rest', function (): void {
    logSentAt($this->user, 'welcome_drip_1', now()->subDays(89));
    logSentAt($this->user, 'welcome_drip_3', now()->subDays(91));

    expect((new EmailSendLog)->prunable()->count())->toBe(1);
});

/**
 * A send that already happened, dated precisely — cheaper and more readable
 * here than travelling the clock for every cooldown case.
 */
function logSentAt(User $user, string $mailKey, Carbon $sentAt): void
{
    EmailSendLog::query()->create([
        'user_id' => $user->id,
        'mail_key' => $mailKey,
        'sent_at' => $sentAt,
    ]);
}
