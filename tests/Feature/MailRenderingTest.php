<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mail\AbandonedSearchMail;
use App\Mail\AccountDeletedMail;
use App\Mail\AdApprovedMail;
use App\Mail\AdDeclinedMail;
use App\Mail\AdminActionNotifyMail;
use App\Mail\AdminActionPerformedMail;
use App\Mail\AdminWelcomeEmail;
use App\Mail\AdReportReceivedMail;
use App\Mail\AdSubmissionConfirmationMail;
use App\Mail\AdUnlockConfirmationMail;
use App\Mail\AgencyWelcomeEmail;
use App\Mail\AppointmentReminderMail;
use App\Mail\BailleurWelcomeEmail;
use App\Mail\CreditPurchaseConfirmationMail;
use App\Mail\EmailUpdatedMail;
use App\Mail\FirstAdCelebrationMail;
use App\Mail\ForgotPasswordMail;
use App\Mail\GdprDataExportMail;
use App\Mail\InvitationMail;
use App\Mail\MagicLinkSignInMail;
use App\Mail\MagicLinkSignUpMail;
use App\Mail\NewAdReportMail;
use App\Mail\NewAdSubmissionMail;
use App\Mail\NewDeviceSignInMail;
use App\Mail\NewLocationSignInMail;
use App\Mail\OwnerActivityMail;
use App\Mail\PasskeyChangedMail;
use App\Mail\PasswordChangedMail;
use App\Mail\PostViewingFeedbackMail;
use App\Mail\PricingVerificationMail;
use App\Mail\RefundConfirmationMail;
use App\Mail\ResetPasswordMail;
use App\Mail\SearchAlertMatchMail;
use App\Mail\SubscriptionExpiringEmail;
use App\Mail\SubscriptionInvoiceMail;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Mail\SubscriptionSuccessEmail;
use App\Mail\SurveyAdminNotificationMail;
use App\Mail\SurveySubmittedMail;
use App\Mail\VerificationCodeMail;
use App\Mail\VerifyEmailMail;
use App\Mail\WelcomeEmail;
use App\Models\Ad;
use App\Models\AdReport;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PointPackage;
use App\Models\Refund;
use App\Models\SearchAlert;
use App\Models\Subscription;
use App\Models\Survey;
use App\Models\User;
use App\Support\MailTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Ad-Related Emails ──────────────────────────────────────────────────────────

it('renders AdApprovedMail without errors', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $mail = new AdApprovedMail($ad);
    $rendered = $mail->render();

    expect($rendered)->toContain($ad->title);
    expect($mail->envelope()->subject)->toContain('en ligne');
});

it('renders AdDeclinedMail without errors', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $mail = new AdDeclinedMail($ad, 'Contenu inapproprié');
    $rendered = $mail->render();

    expect($rendered)->toContain($ad->title);
    expect($mail->envelope()->subject)->toContain('Action requise');
});

it('renders AdDeclinedMail with empty reason', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $mail = new AdDeclinedMail($ad);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders AdSubmissionConfirmationMail without errors', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $mail = new AdSubmissionConfirmationMail($ad);
    $rendered = $mail->render();

    expect($rendered)->toContain($ad->title);
    expect($mail->envelope()->subject)->toContain('validation');
});

it('renders NewAdSubmissionMail without errors', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $mail = new NewAdSubmissionMail($ad);
    $rendered = $mail->render();

    expect($rendered)->toContain($ad->title);
});

it('renders AdReportReceivedMail without errors', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $report = AdReport::factory()->create([
        'ad_id' => $ad->id,
        'reporter_id' => $user->id,
    ]);

    $mail = new AdReportReceivedMail($report);
    $rendered = $mail->render();

    expect($rendered)->toContain('RPT-');
});

it('renders NewAdReportMail without errors', function (): void {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $report = AdReport::factory()->create([
        'ad_id' => $ad->id,
        'reporter_id' => $user->id,
    ]);

    $mail = new NewAdReportMail($report, $admin);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

// ── Welcome Emails ─────────────────────────────────────────────────────────────

it('renders WelcomeEmail without errors', function (): void {
    $user = User::factory()->create();

    $mail = new WelcomeEmail($user);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
    expect($mail->envelope()->subject)->toContain('activé');
});

it('renders AgencyWelcomeEmail without errors', function (): void {
    $user = User::factory()->create();

    $mail = new AgencyWelcomeEmail($user);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
    expect($mail->envelope()->subject)->toContain('agence');
});

it('renders BailleurWelcomeEmail without errors', function (): void {
    $user = User::factory()->create();

    $mail = new BailleurWelcomeEmail($user);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
    expect($mail->envelope()->subject)->toContain('bailleur');
});

// ── Auth & Security Emails ─────────────────────────────────────────────────────

it('renders VerificationCodeMail without errors', function (): void {
    $mail = new VerificationCodeMail('123456', 'test@keyhome.cm', now()->toDateTimeString());
    $rendered = $mail->render();

    expect($rendered)->toContain('123456');
});

it('renders ForgotPasswordMail without errors', function (): void {
    $mail = new ForgotPasswordMail('https://keyhome.cm/reset?token=abc123', 'test@keyhome.cm', now()->toDateTimeString());
    $rendered = $mail->render();

    expect($rendered)->toContain('reset');
});

it('renders MagicLinkSignInMail without errors', function (): void {
    $mail = new MagicLinkSignInMail('https://keyhome.cm/magic-link?token=abc', 15, '127.0.0.1', now()->toDateTimeString());
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders MagicLinkSignUpMail without errors', function (): void {
    $mail = new MagicLinkSignUpMail('https://keyhome.cm/magic-link?token=abc', 15, '127.0.0.1', now()->toDateTimeString());
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

// ── Payment Emails ─────────────────────────────────────────────────────────────

it('renders CreditPurchaseConfirmationMail without errors', function (): void {
    $user = User::factory()->create();
    $package = PointPackage::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $user->id]);

    $mail = new CreditPurchaseConfirmationMail($user, $package, $payment, 500);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
    expect($mail->envelope()->subject)->toContain('crédits');
});

// ── Survey Emails ──────────────────────────────────────────────────────────────

it('renders SurveySubmittedMail without errors', function (): void {
    $user = User::factory()->create();
    $survey = Survey::factory()->create();

    $mail = new SurveySubmittedMail($survey, $user);
    $rendered = $mail->render();

    expect($rendered)->toContain($survey->title);
});

it('renders SurveyAdminNotificationMail without errors', function (): void {
    $user = User::factory()->create();
    $survey = Survey::factory()->create();

    $mail = new SurveyAdminNotificationMail($survey, $user, [
        ['question' => 'How satisfied?', 'answer' => 'Very'],
    ]);
    $rendered = $mail->render();

    expect($rendered)->toContain($survey->title);
});

// ── Admin Action Emails ────────────────────────────────────────────────────────

it('renders AdminActionNotifyMail without errors', function (): void {
    $actor = User::factory()->create();
    $recipient = User::factory()->create();

    $mail = new AdminActionNotifyMail($actor, $recipient, [
        'event' => 'updated',
        'entity' => 'Ad',
        'entity_name' => 'Appartement 3 pièces',
        'description' => 'Ad was approved',
        'changes' => ['status' => ['pending', 'approved']],
        'date' => now()->toDateTimeString(),
    ]);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders AdminActionPerformedMail without errors', function (): void {
    $actor = User::factory()->create();

    $mail = new AdminActionPerformedMail($actor, [
        'event' => 'deleted',
        'entity' => 'User',
        'entity_name' => 'john@example.com',
        'description' => 'User was deleted',
        'changes' => [],
        'date' => now()->toDateTimeString(),
    ]);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

// ── Auth & Security Emails (Extended) ──────────────────────────────────────────

it('renders AdminWelcomeEmail without errors', function (): void {
    $user = User::factory()->create();

    $mail = new AdminWelcomeEmail($user);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
    expect($mail->envelope()->subject)->toContain('administrateur');
});

it('renders ResetPasswordMail without errors', function (): void {
    $mail = new ResetPasswordMail('654321', 'test@keyhome.cm', now()->toDateTimeString());
    $rendered = $mail->render();

    expect($rendered)->toContain('654321');
});

it('renders VerifyEmailMail without errors', function (): void {
    $mail = new VerifyEmailMail('https://keyhome.cm/verify?token=abc', 60, 'test@keyhome.cm', now()->toDateTimeString());
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders NewLocationSignInMail without errors', function (): void {
    $mail = new NewLocationSignInMail('John', 'Douala', 'Cameroon', '1.2.3.4', 'iPhone', 'Safari', 'iOS', now()->toDateTimeString());
    $rendered = $mail->render();

    expect($rendered)->toContain('Douala');
});

it('renders PasskeyChangedMail without errors', function (): void {
    $mail = new PasskeyChangedMail('MacBook Touch ID', 'john@keyhome.cm', 'added');
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders PasswordChangedMail without errors', function (): void {
    $mail = new PasswordChangedMail('john@keyhome.cm', 'John');
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders EmailUpdatedMail without errors', function (): void {
    $mail = new EmailUpdatedMail('new@keyhome.cm');
    $rendered = $mail->render();

    expect($rendered)->toContain('new@keyhome.cm');
});

it('renders NewDeviceSignInMail without errors', function (): void {
    $mail = new NewDeviceSignInMail('Desktop', 'Chrome', 'Windows', 'Douala, CM', '1.2.3.4', now()->toDateTimeString());
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders InvitationMail without errors', function (): void {
    $mail = new InvitationMail('https://keyhome.cm/invite?token=abc', 7, 'Admin User');
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

// ── Payment & Subscription Emails ──────────────────────────────────────────────

it('renders AdUnlockConfirmationMail without errors', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $payment = Payment::factory()->create(['user_id' => $user->id]);

    $mail = new AdUnlockConfirmationMail($user, $ad, $payment);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders PricingVerificationMail without errors', function (): void {
    $user = User::factory()->create();

    $mail = new PricingVerificationMail($user, 'VER-12345');
    $rendered = $mail->render();

    expect($rendered)->toContain('VER-12345');
});

it('renders RefundConfirmationMail without errors', function (): void {
    $user = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $user->id]);
    $refund = Refund::factory()->create([
        'payment_id' => $payment->id,
        'user_id' => $user->id,
    ]);

    $mail = new RefundConfirmationMail($refund);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders SubscriptionSuccessEmail without errors', function (): void {
    $subscription = Subscription::factory()->create();

    $mail = new SubscriptionSuccessEmail($subscription);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders SubscriptionInvoiceMail without errors', function (): void {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();

    $mail = new SubscriptionInvoiceMail($user, $invoice);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders SubscriptionExpiringEmail without errors', function (): void {
    $subscription = Subscription::factory()->create();

    $mail = new SubscriptionExpiringEmail($subscription, 7);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders SubscriptionRenewalReminderMail without errors', function (): void {
    $subscription = Subscription::factory()->create();

    $mail = new SubscriptionRenewalReminderMail($subscription, 'https://keyhome.cm/renew');
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

// ── Engagement Emails ──────────────────────────────────────────────────────────

it('renders AbandonedSearchMail without errors', function (): void {
    $user = User::factory()->create();

    $mail = new AbandonedSearchMail($user, 15, 'https://keyhome.cm/search?q=douala');
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('prints the listings the customer looked at in AbandonedSearchMail', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['title' => 'Duplex Bonamoussadi', 'status' => 'available']);

    $rendered = new AbandonedSearchMail($user, 15, 'https://keyhome.cm/search', [$ad])->render();

    expect($rendered)->toContain('Duplex Bonamoussadi')
        ->and($rendered)->toContain('/ads/'.$ad->slug);
});

it('flattens a listing into strings the queued mail can print on its own', function (): void {
    // The job renders long after the command ran, so the card carries plain
    // strings rather than an Ad that may have moved on since.
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['price' => 250000, 'status' => 'available']);

    $mail = new AbandonedSearchMail($user, 1, 'https://keyhome.cm/search', [$ad]);
    $card = $mail->adCards[0];

    expect($card['price'])->toBe('250 000 FCFA')
        ->and($card['location'])->toContain((string) $ad->quarter->name)
        ->and($card['url'])->toContain('/ads/'.$ad->slug);
});

it('offers to talk about the price when a listing names none', function (): void {
    // An empty price line reads like a broken template, so the copy says something.
    $user = User::factory()->create();
    $ad = Ad::factory()->create(['price' => null, 'status' => 'available']);

    $mail = new AbandonedSearchMail($user, 1, 'https://keyhome.cm/search', [$ad]);

    expect($mail->adCards[0]['price'])->toBe(__('emails.components.price_on_request'));
});

it('dresses AbandonedSearchMail in the client palette', function (): void {
    $user = User::factory()->create();

    $rendered = new AbandonedSearchMail($user, 3, 'https://keyhome.cm/search')->render();

    expect($rendered)->toContain(MailTheme::client()['accent'])
        ->and($rendered)->not->toContain(MailTheme::owner()['accent']);
});

it('renders OwnerActivityMail without errors', function (): void {
    $owner = User::factory()->agents()->create();
    $ad = Ad::factory()->for($owner)->create(['title' => 'Villa Bonapriso']);

    $rendered = new OwnerActivityMail($owner, 42, 5, 2, [$ad])->render();

    expect($rendered)->toContain('42')
        ->and($rendered)->toContain('Villa Bonapriso');
});

it('dresses OwnerActivityMail in the landlord palette and never the client one', function (): void {
    // The audience split is the whole reason MailTheme exists: a landlord must
    // never open a coral mail, nor a client a teal one.
    $owner = User::factory()->agents()->create();

    $rendered = new OwnerActivityMail($owner, 42)->render();

    expect($rendered)->toContain(MailTheme::owner()['accent'])
        ->and($rendered)->not->toContain(MailTheme::client()['accent']);
});

it('renders AppointmentReminderMail without errors', function (): void {
    $user = User::factory()->create();

    $mail = new AppointmentReminderMail($user, 'Appartement 3P Bonamoussadi', now()->addDay(), 'Rue 1.234, Douala', 'https://keyhome.cm/viewings/1');
    $rendered = $mail->render();

    expect($rendered)->toContain('Bonamoussadi');
});

it('renders PostViewingFeedbackMail without errors', function (): void {
    $user = User::factory()->create();

    $mail = new PostViewingFeedbackMail($user, 'Appartement 3P Bonamoussadi', 'https://keyhome.cm/feedback/1', 'https://keyhome.cm/search');
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

it('renders SearchAlertMatchMail without errors', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();
    $alert = SearchAlert::factory()->create(['user_id' => $user->id]);

    $mail = new SearchAlertMatchMail($ad, $alert, $user);
    $rendered = $mail->render();

    expect($rendered)->toBeString();
});

// ── Unsubscribe Links ──────────────────────────────────────────────────────────

it('includes unsubscribe link in marketing email', function (): void {
    $user = User::factory()->create();
    $ad = Ad::factory()->for($user)->create();

    $mail = new AdApprovedMail($ad);
    $rendered = $mail->render();

    expect($rendered)->toContain('Se désabonner');
    expect($rendered)->toContain('Gérer mes préférences');
});

it('includes unsubscribe link in welcome email', function (): void {
    $user = User::factory()->create();

    $mail = new WelcomeEmail($user);
    $rendered = $mail->render();

    expect($rendered)->toContain('Se désabonner');
});

it('does not include unsubscribe link in verification email', function (): void {
    $mail = new VerificationCodeMail('123456', 'test@keyhome.cm', now()->toDateTimeString());
    $rendered = $mail->render();

    expect($rendered)->not->toContain('Se désabonner');
});

// ── Dark Mode ──────────────────────────────────────────────────────────────────

it('includes dark mode meta tag in email layout', function (): void {
    $user = User::factory()->create();

    $mail = new WelcomeEmail($user);
    $rendered = $mail->render();

    expect($rendered)->toContain('color-scheme');
    expect($rendered)->toContain('prefers-color-scheme: dark');
});

// ── New owner mails ────────────────────────────────────────────────────────────

it('renders FirstAdCelebrationMail without errors', function (): void {
    $user = User::factory()->agents()->create();
    $ad = Ad::factory()->for($user)->create();

    $mail = new FirstAdCelebrationMail($ad);
    $rendered = $mail->render();

    expect($rendered)->toContain($user->firstname);
    expect($rendered)->toContain($ad->title);
    expect($mail->envelope()->subject)->toContain('première annonce');
});

it('renders AccountDeletedMail for customer (primary layout)', function (): void {
    $mail = new AccountDeletedMail(
        userName: 'Jean',
        userEmail: 'jean@example.com',
        userRole: UserRole::CUSTOMER,
    );
    $rendered = $mail->render();

    expect($rendered)->toContain('Jean');
    expect($rendered)->toContain('supprimé');
    expect($mail->envelope()->subject)->toContain('supprimé');
});

it('renders AccountDeletedMail for owner (teal layout)', function (): void {
    $mail = new AccountDeletedMail(
        userName: 'Paul',
        userEmail: 'paul@example.com',
        userRole: UserRole::AGENT,
    );
    $rendered = $mail->render();

    expect($rendered)->toContain('Paul');
    expect($rendered)->toContain('propriétaire');
    expect($mail->envelope()->subject)->toContain('supprimé');
});

it('renders GdprDataExportMail without errors and has json attachment', function (): void {
    $user = User::factory()->create();

    $mail = new GdprDataExportMail($user);
    $rendered = $mail->render();

    expect($rendered)->toContain($user->firstname);
    expect($mail->envelope()->subject)->toContain('RGPD');

    $attachments = $mail->attachments();
    expect($attachments)->toHaveCount(1);
    expect($attachments[0]->as)->toContain('.json');
});
