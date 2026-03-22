<?php

use App\Console\Commands\SendEngagementEmails;
use App\Mail\FailedPaymentRetryMail;
use App\Mail\InactivityReminderMail;
use App\Mail\WeeklyDigestMail;
use App\Mail\WelcomeDripMail;
use App\Models\EmailPreference;
use App\Models\Payment;
use App\Models\SearchAlert;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Welcome Drip Emails
|--------------------------------------------------------------------------
*/

it('queues a day-1 welcome drip email for users created 1 day ago', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'created_at' => now()->subDays(1)->subHours(2),
    ]);
    EmailPreference::getOrCreateForUser($user);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'drip'])
        ->assertSuccessful();

    Mail::assertQueued(WelcomeDripMail::class, fn ($mail) => $mail->user->id === $user->id && $mail->day === 1);
});

it('queues a day-3 welcome drip email for users created 3 days ago', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'created_at' => now()->subDays(3)->subHours(2),
    ]);
    EmailPreference::getOrCreateForUser($user);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'drip'])
        ->assertSuccessful();

    Mail::assertQueued(WelcomeDripMail::class, fn ($mail) => $mail->user->id === $user->id && $mail->day === 3);
});

it('queues a day-7 welcome drip email for users created 7 days ago', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'created_at' => now()->subDays(7)->subHours(2),
    ]);
    EmailPreference::getOrCreateForUser($user);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'drip'])
        ->assertSuccessful();

    Mail::assertQueued(WelcomeDripMail::class, fn ($mail) => $mail->user->id === $user->id && $mail->day === 7);
});

it('does not send welcome drip when engagement_emails preference is disabled', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'created_at' => now()->subDays(1)->subHours(2),
    ]);
    $pref = EmailPreference::getOrCreateForUser($user);
    $pref->engagement_emails = false;
    $pref->save();

    $this->artisan(SendEngagementEmails::class, ['--type' => 'drip'])
        ->assertSuccessful();

    Mail::assertNotQueued(WelcomeDripMail::class);
});

it('does not send welcome drip for users created at a non-matching time', function (): void {
    Mail::fake();

    User::factory()->create(['created_at' => now()->subDays(2)->subHours(2)]);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'drip'])
        ->assertSuccessful();

    Mail::assertNotQueued(WelcomeDripMail::class);
});

/*
|--------------------------------------------------------------------------
| Inactivity Reminder Emails
|--------------------------------------------------------------------------
*/

it('queues an inactivity reminder for users inactive for 30 days', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'last_home_visit_at' => now()->subDays(30)->subHours(2),
    ]);
    EmailPreference::getOrCreateForUser($user);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'inactivity'])
        ->assertSuccessful();

    Mail::assertQueued(InactivityReminderMail::class, fn ($mail) => $mail->user->id === $user->id);
});

it('queues an inactivity reminder for users with no last_home_visit_at created 30 days ago', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'last_home_visit_at' => null,
        'created_at' => now()->subDays(30)->subHours(2),
    ]);
    EmailPreference::getOrCreateForUser($user);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'inactivity'])
        ->assertSuccessful();

    Mail::assertQueued(InactivityReminderMail::class, fn ($mail) => $mail->user->id === $user->id);
});

it('does not send inactivity reminder when engagement_emails preference is disabled', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'last_home_visit_at' => now()->subDays(30)->subHours(2),
    ]);
    $pref = EmailPreference::getOrCreateForUser($user);
    $pref->engagement_emails = false;
    $pref->save();

    $this->artisan(SendEngagementEmails::class, ['--type' => 'inactivity'])
        ->assertSuccessful();

    Mail::assertNotQueued(InactivityReminderMail::class);
});

it('does not send inactivity reminder for recently active users', function (): void {
    Mail::fake();

    User::factory()->create(['last_home_visit_at' => now()->subHours(2)]);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'inactivity'])
        ->assertSuccessful();

    Mail::assertNotQueued(InactivityReminderMail::class);
});

/*
|--------------------------------------------------------------------------
| Failed Payment Retry Emails
|--------------------------------------------------------------------------
*/

it('queues a failed payment retry email for recent failed payments', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    $payment = Payment::factory()->failed()->create([
        'user_id' => $user->id,
        'updated_at' => now()->subHours(5),
    ]);
    EmailPreference::getOrCreateForUser($user);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'failed-payment'])
        ->assertSuccessful();

    Mail::assertQueued(FailedPaymentRetryMail::class, fn ($mail) => $mail->payment->id === $payment->id);
});

it('does not send failed payment retry when engagement_emails preference is disabled', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    Payment::factory()->failed()->create([
        'user_id' => $user->id,
        'updated_at' => now()->subHours(5),
    ]);
    $pref = EmailPreference::getOrCreateForUser($user);
    $pref->engagement_emails = false;
    $pref->save();

    $this->artisan(SendEngagementEmails::class, ['--type' => 'failed-payment'])
        ->assertSuccessful();

    Mail::assertNotQueued(FailedPaymentRetryMail::class);
});

it('does not send failed payment retry for old failed payments', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    Payment::factory()->failed()->create([
        'user_id' => $user->id,
        'updated_at' => now()->subDays(5),
    ]);
    EmailPreference::getOrCreateForUser($user);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'failed-payment'])
        ->assertSuccessful();

    Mail::assertNotQueued(FailedPaymentRetryMail::class);
});

/*
|--------------------------------------------------------------------------
| Weekly Digest Emails
|--------------------------------------------------------------------------
*/

it('queues a weekly digest email for users with active search alerts', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    SearchAlert::create([
        'user_id' => $user->id,
        'label' => 'Test Alert',
        'is_active' => true,
        'last_notified_at' => now()->subDays(3),
    ]);
    EmailPreference::getOrCreateForUser($user);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'digest'])
        ->assertSuccessful();

    Mail::assertQueued(WeeklyDigestMail::class, fn ($mail) => $mail->user->id === $user->id);
});

it('does not send weekly digest when digest_emails preference is disabled', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    SearchAlert::create([
        'user_id' => $user->id,
        'label' => 'Test Alert',
        'is_active' => true,
        'last_notified_at' => now()->subDays(3),
    ]);
    $pref = EmailPreference::getOrCreateForUser($user);
    $pref->digest_emails = false;
    $pref->save();

    $this->artisan(SendEngagementEmails::class, ['--type' => 'digest'])
        ->assertSuccessful();

    Mail::assertNotQueued(WeeklyDigestMail::class);
});

it('does not send weekly digest for users without active search alerts', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    SearchAlert::create([
        'user_id' => $user->id,
        'label' => 'Inactive Alert',
        'is_active' => false,
        'last_notified_at' => now()->subDays(3),
    ]);
    EmailPreference::getOrCreateForUser($user);

    $this->artisan(SendEngagementEmails::class, ['--type' => 'digest'])
        ->assertSuccessful();

    Mail::assertNotQueued(WeeklyDigestMail::class);
});
