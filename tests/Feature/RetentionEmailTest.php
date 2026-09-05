<?php

declare(strict_types=1);

use App\Console\Commands\SendEngagementEmails;
use App\Mail\AbandonedSearchMail;
use App\Mail\InactivityReminderMail;
use App\Mail\OwnerActivityMail;
use App\Mail\WeeklyDigestMail;
use App\Mail\WelcomeDripMail;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\Conversation;
use App\Models\EmailPreference;
use App\Models\EmailSendLog;
use App\Models\SearchAlert;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * A customer who was shopping the day before yesterday and has not come back.
 *
 * @param  array<string, mixed>  $attributes
 */
function warmLeadCustomer(array $attributes = []): User
{
    $user = User::factory()->customers()->create([
        'last_home_visit_at' => now()->subDays(2)->subHours(3),
        ...$attributes,
    ]);

    EmailPreference::getOrCreateForUser($user);

    return $user;
}

/**
 * A landlord whose last sign-in was two days ago.
 *
 * @param  array<string, mixed>  $attributes
 */
function awayOwner(array $attributes = []): User
{
    $owner = User::factory()->agents()->create([
        'last_seen_at' => now()->subDays(2)->subHours(3),
        ...$attributes,
    ]);

    EmailPreference::getOrCreateForUser($owner);

    return $owner;
}

/**
 * A listing the public can actually reach, so a mail is allowed to print it.
 *
 * @param  array<string, mixed>  $attributes
 */
function listedAd(array $attributes = []): Ad
{
    return Ad::factory()->create([
        'status' => 'available',
        'is_visible' => true,
        ...$attributes,
    ]);
}

function recordIntent(User $user, ?Ad $ad, string $type, ?Carbon $at = null): void
{
    AdInteraction::create([
        'user_id' => $user->id,
        'ad_id' => $ad?->id,
        'type' => $type,
        'created_at' => $at ?? now()->subDays(2),
    ]);
}

/*
|--------------------------------------------------------------------------
| Warm-lead win-back (48 h)
|--------------------------------------------------------------------------
| Deliberately narrow: the recipient must have shown intent in the last
| week. Mailing everyone who registered and never opened a single listing
| is how a sending domain earns a spam reputation.
*/

it('queues the win-back for a customer who was shopping two days ago', function (): void {
    Mail::fake();

    $user = warmLeadCustomer();
    recordIntent($user, listedAd(), AdInteraction::TYPE_VIEW);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])
        ->assertSuccessful();

    Mail::assertQueued(AbandonedSearchMail::class, fn ($mail) => $mail->user->id === $user->id);
});

it('names the listings the customer actually opened', function (): void {
    Mail::fake();

    $user = warmLeadCustomer();
    $ad = listedAd(['title' => 'Studio meuble Bonapriso']);
    recordIntent($user, $ad, AdInteraction::TYPE_VIEW);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])
        ->assertSuccessful();

    Mail::assertQueued(AbandonedSearchMail::class, fn ($mail) => count($mail->adCards) === 1
        && $mail->adCards[0]['title'] === 'Studio meuble Bonapriso'
        && str_contains((string) $mail->adCards[0]['url'], (string) $ad->slug));
});

it('leaves a customer who never looked at anything alone', function (): void {
    // The whole point of the targeting: no interaction, nothing to say.
    Mail::fake();

    warmLeadCustomer();

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])
        ->assertSuccessful();

    Mail::assertNotQueued(AbandonedSearchMail::class);
});

it('leaves a customer whose last look was over a week ago alone', function (): void {
    Mail::fake();

    $user = warmLeadCustomer();
    recordIntent($user, listedAd(), AdInteraction::TYPE_VIEW, now()->subDays(9));

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])
        ->assertSuccessful();

    Mail::assertNotQueued(AbandonedSearchMail::class);
});

it('counts a search as intent even though it names no listing', function (): void {
    Mail::fake();

    $user = warmLeadCustomer();
    recordIntent($user, null, AdInteraction::TYPE_SEARCH);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])
        ->assertSuccessful();

    // Someone was shopping, so the mail goes; it just has no cards to show.
    Mail::assertQueued(AbandonedSearchMail::class, fn ($mail) => $mail->adCards === []);
});

it('ignores an impression, which is only something they scrolled past', function (): void {
    Mail::fake();

    $user = warmLeadCustomer();
    recordIntent($user, listedAd(), AdInteraction::TYPE_IMPRESSION);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])
        ->assertSuccessful();

    Mail::assertNotQueued(AbandonedSearchMail::class);
});

it('does not fire the morning after a visit', function (): void {
    Mail::fake();

    $user = warmLeadCustomer(['last_home_visit_at' => now()->subDay()]);
    recordIntent($user, listedAd(), AdInteraction::TYPE_VIEW, now()->subDay());

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])
        ->assertSuccessful();

    Mail::assertNotQueued(AbandonedSearchMail::class);
});

it('keeps a withdrawn listing out of the card list', function (): void {
    // BUG CATCH: reprinting a listing that has left the market turns a helpful
    // reminder into a dead end.
    Mail::fake();

    $user = warmLeadCustomer();
    recordIntent($user, listedAd(['is_visible' => false]), AdInteraction::TYPE_VIEW);
    recordIntent($user, listedAd(['title' => 'Appartement 3P Akwa']), AdInteraction::TYPE_FAVORITE);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])
        ->assertSuccessful();

    Mail::assertQueued(AbandonedSearchMail::class, fn ($mail) => count($mail->adCards) === 1
        && $mail->adCards[0]['title'] === 'Appartement 3P Akwa');
});

it('counts the listings that went up while they were away', function (): void {
    Mail::fake();

    $user = warmLeadCustomer();
    recordIntent($user, listedAd(['created_at' => now()->subDays(10)]), AdInteraction::TYPE_VIEW);
    listedAd(['created_at' => now()->subDay()]);
    listedAd(['created_at' => now()->subDays(10)]);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])
        ->assertSuccessful();

    Mail::assertQueued(AbandonedSearchMail::class, fn ($mail) => $mail->matchingAdsCount === 1);
});

it('does not queue the win-back twice', function (): void {
    Mail::fake();

    $user = warmLeadCustomer();
    recordIntent($user, listedAd(), AdInteraction::TYPE_VIEW);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])->assertSuccessful();
    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])->assertSuccessful();

    Mail::assertQueued(AbandonedSearchMail::class, 1);
});

it('does not win back a customer who opted out of engagement mail', function (): void {
    Mail::fake();

    $user = warmLeadCustomer();
    EmailPreference::getOrCreateForUser($user)->update(['engagement_emails' => false]);
    recordIntent($user, listedAd(), AdInteraction::TYPE_VIEW);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'warm-lead'])
        ->assertSuccessful();

    Mail::assertNotQueued(AbandonedSearchMail::class);
});

/*
|--------------------------------------------------------------------------
| Owner activity report (48 h)
|--------------------------------------------------------------------------
| The supply side comes back for evidence of demand, not for encouragement,
| so this one reports numbers and is silent when there are none.
*/

it('reports real demand to a landlord who was away', function (): void {
    Mail::fake();

    $owner = awayOwner();
    $ad = listedAd(['user_id' => $owner->id]);
    $visitor = User::factory()->customers()->create();

    recordIntent($visitor, $ad, AdInteraction::TYPE_VIEW, now()->subDay());
    recordIntent($visitor, $ad, AdInteraction::TYPE_VIEW, now()->subHours(6));
    recordIntent($visitor, $ad, AdInteraction::TYPE_FAVORITE, now()->subHours(5));

    $this->artisan(SendEngagementEmails::class, ['--type' => 'owner-activity'])
        ->assertSuccessful();

    Mail::assertQueued(OwnerActivityMail::class, fn ($mail) => $mail->user->id === $owner->id
        && $mail->viewCount === 2
        && $mail->favoriteCount === 1);
});

it('stays quiet when there was nothing to report', function (): void {
    // Inventing enthusiasm teaches the reader to ignore the sender. The
    // D7/D14/D30 track already covers "come back and improve your listings".
    Mail::fake();

    $owner = awayOwner();
    listedAd(['user_id' => $owner->id]);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'owner-activity'])
        ->assertSuccessful();

    Mail::assertNotQueued(OwnerActivityMail::class);
});

it('stays quiet for a landlord with no listings at all', function (): void {
    Mail::fake();

    awayOwner();

    $this->artisan(SendEngagementEmails::class, ['--type' => 'owner-activity'])
        ->assertSuccessful();

    Mail::assertNotQueued(OwnerActivityMail::class);
});

it('only counts what happened while they were away', function (): void {
    Mail::fake();

    $owner = awayOwner();
    $ad = listedAd(['user_id' => $owner->id]);
    $visitor = User::factory()->customers()->create();

    // Views the landlord already saw for themselves must not be re-reported.
    recordIntent($visitor, $ad, AdInteraction::TYPE_VIEW, now()->subDays(5));
    recordIntent($visitor, $ad, AdInteraction::TYPE_VIEW, now()->subDay());

    $this->artisan(SendEngagementEmails::class, ['--type' => 'owner-activity'])
        ->assertSuccessful();

    Mail::assertQueued(OwnerActivityMail::class, fn ($mail) => $mail->viewCount === 1);
});

it('tells the landlord how many conversations are waiting on them', function (): void {
    Mail::fake();

    $owner = awayOwner();
    $ad = listedAd(['user_id' => $owner->id]);
    recordIntent(User::factory()->customers()->create(), $ad, AdInteraction::TYPE_VIEW, now()->subDay());

    Conversation::factory()->create([
        'landlord_id' => $owner->id,
        'last_message_at' => now()->subHours(3),
        'landlord_last_read_at' => null,
    ]);
    Conversation::factory()->create([
        'landlord_id' => $owner->id,
        'last_message_at' => now()->subDays(4),
        'landlord_last_read_at' => now()->subDays(3),
    ]);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'owner-activity'])
        ->assertSuccessful();

    Mail::assertQueued(OwnerActivityMail::class, fn ($mail) => $mail->unansweredMessages === 1);
});

it('puts the busiest listing at the top of the report', function (): void {
    Mail::fake();

    $owner = awayOwner();
    $quiet = listedAd(['user_id' => $owner->id, 'title' => 'Chambre Bepanda']);
    $busy = listedAd(['user_id' => $owner->id, 'title' => 'Villa Bonapriso']);
    $visitor = User::factory()->customers()->create();

    recordIntent($visitor, $quiet, AdInteraction::TYPE_VIEW, now()->subDay());
    recordIntent($visitor, $busy, AdInteraction::TYPE_VIEW, now()->subDay());
    recordIntent($visitor, $busy, AdInteraction::TYPE_VIEW, now()->subHours(4));

    $this->artisan(SendEngagementEmails::class, ['--type' => 'owner-activity'])
        ->assertSuccessful();

    Mail::assertQueued(OwnerActivityMail::class, fn ($mail) => $mail->adCards[0]['title'] === 'Villa Bonapriso');
});

it('does not report to the same landlord twice in a week', function (): void {
    Mail::fake();

    $owner = awayOwner();
    $ad = listedAd(['user_id' => $owner->id]);
    recordIntent(User::factory()->customers()->create(), $ad, AdInteraction::TYPE_VIEW, now()->subDay());

    $this->artisan(SendEngagementEmails::class, ['--type' => 'owner-activity'])->assertSuccessful();
    $this->artisan(SendEngagementEmails::class, ['--type' => 'owner-activity'])->assertSuccessful();

    Mail::assertQueued(OwnerActivityMail::class, 1);
});

/*
|--------------------------------------------------------------------------
| A whole run, seen from one inbox
|--------------------------------------------------------------------------
*/

it('holds unsolicited mail back once the week is full, and still sends what was asked for', function (): void {
    Mail::fake();

    // Eligible for the D7 welcome drip and the D7 inactivity reminder at once,
    // and holding an active search alert on top.
    $user = User::factory()->customers()->create([
        'created_at' => now()->subDays(7)->subHours(2),
        'last_home_visit_at' => now()->subDays(7)->subHours(2),
    ]);
    EmailPreference::getOrCreateForUser($user);
    SearchAlert::create([
        'user_id' => $user->id,
        'label' => 'Douala 2 pieces',
        'is_active' => true,
        'last_notified_at' => now()->subDays(3),
    ]);

    foreach (['inactivity_14', 'owner_activity', 'abandoned_search'] as $mailKey) {
        EmailSendLog::query()->create([
            'user_id' => $user->id,
            'mail_key' => $mailKey,
            'sent_at' => now()->subDay(),
        ]);
    }

    $this->artisan(SendEngagementEmails::class)->assertSuccessful();

    Mail::assertNotQueued(WelcomeDripMail::class);
    Mail::assertNotQueued(InactivityReminderMail::class);
    // The digest is subscribed mail, so a full week does not silence it.
    Mail::assertQueued(WeeklyDigestMail::class);
});
