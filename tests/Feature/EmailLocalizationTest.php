<?php

use App\Mail\ForgotPasswordMail;
use App\Mail\RefundConfirmationMail;
use App\Mail\VerificationCodeMail;
use App\Mail\WelcomeEmail;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\User;

beforeEach(function (): void {
    Setting::set('welcome_bonus_points', 0);
});

/*
|--------------------------------------------------------------------------
| Locale API Endpoint
|--------------------------------------------------------------------------
*/

it('allows authenticated user to update their locale', function (): void {
    $user = User::factory()->create(['locale' => 'fr']);

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/auth/locale', ['locale' => 'en']);

    $response->assertSuccessful();
    $response->assertJsonPath('locale', 'en');
    expect($user->fresh()->locale)->toBe('en');
});

it('rejects invalid locale values', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson('/api/v1/auth/locale', ['locale' => 'de']);

    $response->assertUnprocessable();
});

it('defaults locale to fr for new users', function (): void {
    $user = User::factory()->create();

    expect($user->fresh()->locale)->toBe('fr');
});

/*
|--------------------------------------------------------------------------
| Translation Files
|--------------------------------------------------------------------------
*/

it('has both fr and en email translation files', function (): void {
    $fr = trans('emails.welcome.subject', ['app' => 'Test'], 'fr');
    $en = trans('emails.welcome.subject', ['app' => 'Test'], 'en');

    expect($fr)->toBe('Bienvenue sur Test');
    expect($en)->toBe('Welcome to Test');
});

it('translates layout footer in both languages', function (): void {
    expect(trans('emails.layout.rights', [], 'fr'))->toBe('Tous droits réservés.');
    expect(trans('emails.layout.rights', [], 'en'))->toBe('All rights reserved.');

    expect(trans('emails.layout.unsubscribe', [], 'fr'))->toBe('Se désabonner');
    expect(trans('emails.layout.unsubscribe', [], 'en'))->toBe('Unsubscribe');
});

it('translates all major email subjects in both locales', function (): void {
    $subjects = [
        'emails.verification_code.subject',
        'emails.forgot_password.subject',
        'emails.refund.subject',
        'emails.search_alert.subject',
        'emails.ad_approved.subject',
    ];

    foreach ($subjects as $key) {
        $fr = trans($key, ['app' => 'Test', 'code' => '123456', 'amount' => '5000', 'name' => 'Pack'], 'fr');
        $en = trans($key, ['app' => 'Test', 'code' => '123456', 'amount' => '5000', 'name' => 'Pack'], 'en');

        expect($fr)->not->toBe($en, "Translation missing for {$key}");
    }
});

/*
|--------------------------------------------------------------------------
| WelcomeEmail Locale — rendered via withLocale
|--------------------------------------------------------------------------
*/

it('renders welcome email with French subject for fr-locale user', function (): void {
    $user = User::factory()->create(['locale' => 'fr']);
    $mail = new WelcomeEmail($user);
    $appName = config('app.name');

    $rendered = $mail->render();

    expect($rendered)->toContain(__('emails.welcome.heading', ['name' => $user->lastname], 'fr'));
});

it('renders welcome email with English content for en-locale user', function (): void {
    $user = User::factory()->create(['locale' => 'en']);
    $mail = new WelcomeEmail($user);

    $rendered = $mail->render();

    expect($rendered)->toContain('Welcome,');
    expect($rendered)->toContain('Smart search');
    expect($rendered)->toContain('All rights reserved.');
});

it('renders welcome email with French content for fr-locale user', function (): void {
    $user = User::factory()->create(['locale' => 'fr']);
    $mail = new WelcomeEmail($user);

    $rendered = $mail->render();

    expect($rendered)->toContain('Bienvenue,');
    expect($rendered)->toContain('Rechercher intelligemment');
    expect($rendered)->toContain('Tous droits réservés.');
});

/*
|--------------------------------------------------------------------------
| VerificationCodeMail — default French
|--------------------------------------------------------------------------
*/

it('renders verification code email in French by default', function (): void {
    $mail = new VerificationCodeMail('123456', '127.0.0.1', 'now');

    $rendered = $mail->render();

    expect($rendered)->toContain('Code de vérification');
    expect($rendered)->toContain('123456');
});

/*
|--------------------------------------------------------------------------
| ForgotPasswordMail — default French
|--------------------------------------------------------------------------
*/

it('renders forgot password email in French by default', function (): void {
    $mail = new ForgotPasswordMail('https://example.com/reset', '127.0.0.1', 'now');

    $rendered = $mail->render();

    expect($rendered)->toContain('Réinitialisation du mot de passe');
});

/*
|--------------------------------------------------------------------------
| RefundConfirmationMail — locale from user
|--------------------------------------------------------------------------
*/

it('renders refund email in English for en-locale user', function (): void {
    $user = User::factory()->create(['locale' => 'en']);
    $refund = Refund::factory()->create(['user_id' => $user->id, 'amount' => 5000]);

    $mail = new RefundConfirmationMail($refund);
    $rendered = $mail->render();

    expect($rendered)->toContain('Amount refunded');
    expect($rendered)->toContain('Payment reference');
});

it('renders refund email in French for fr-locale user', function (): void {
    $user = User::factory()->create(['locale' => 'fr']);
    $refund = Refund::factory()->create(['user_id' => $user->id, 'amount' => 5000]);

    $mail = new RefundConfirmationMail($refund);
    $rendered = $mail->render();

    expect($rendered)->toContain('Montant remboursé');
    expect($rendered)->toContain('Référence paiement');
});

/*
|--------------------------------------------------------------------------
| HasLocale Trait — verifies locale is set on mailable
|--------------------------------------------------------------------------
*/

it('applies locale from user to mailable via HasLocale trait', function (): void {
    $enUser = User::factory()->create(['locale' => 'en']);
    $frUser = User::factory()->create(['locale' => 'fr']);

    $enMail = new WelcomeEmail($enUser);
    $frMail = new WelcomeEmail($frUser);

    // The locale property is set on the mailable
    expect($enMail->locale)->toBe('en');
    expect($frMail->locale)->toBe('fr');
});
