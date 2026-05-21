<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mail\AccountDeletedMail;
use App\Mail\AdApprovedMail;
use App\Mail\FirstAdUnlockCongratulationsMail;
use App\Mail\ForgotPasswordMail;
use App\Mail\ResetPasswordMail;
use App\Mail\VerificationCodeMail;
use App\Mail\WelcomeDripMail;
use App\Models\Ad;
use App\Models\User;

/**
 * Email rendering smoke tests.
 *
 * Renders every previewable Mailable and asserts:
 *  - No render exceptions (broken Blade syntax, missing variables)
 *  - Output contains <!DOCTYPE html> and </html> (complete document)
 *  - Contains the app name in the footer
 *  - MSO conditional comment present (Outlook compat)
 */
dataset('mailables', function () {
    $now = now()->translatedFormat('d F Y à H:i');
    $ip = '127.0.0.1';

    return [
        'verification-code (client)' => [fn () => new VerificationCodeMail('123456', $ip, $now, 'customer')],
        'verification-code (owner)' => [fn () => new VerificationCodeMail('123456', $ip, $now, 'agent')],
        'reset-password (client)' => [fn () => new ResetPasswordMail('654321', $ip, $now, 'customer')],
        'reset-password (owner)' => [fn () => new ResetPasswordMail('654321', $ip, $now, 'agent')],
        'forgot-password (client)' => [fn () => new ForgotPasswordMail('https://keyhome.app/reset?token=fake', $ip, $now, 'customer')],
        'forgot-password (owner)' => [fn () => new ForgotPasswordMail('https://keyhome.app/reset?token=fake', $ip, $now, 'agent')],
        'account-deleted (client)' => [fn () => new AccountDeletedMail('Jean Dupont', 'jean@example.com', UserRole::CUSTOMER)],
        'account-deleted (owner)' => [fn () => new AccountDeletedMail('Jean Dupont', 'jean@example.com', UserRole::AGENT)],
        'welcome-drip day 1' => [fn () => new WelcomeDripMail(User::factory()->create(), 1)],
        'welcome-drip day 3' => [fn () => new WelcomeDripMail(User::factory()->create(), 3)],
        'welcome-drip day 7' => [fn () => new WelcomeDripMail(User::factory()->create(), 7)],
        'first-ad-unlock congratulations' => [
            fn () => new FirstAdUnlockCongratulationsMail(
                User::factory()->customers()->create(),
                Ad::factory()->for(User::factory()->agents()->create())->create(['slug' => 'annonce-demo-slug']),
            ),
        ],
        'ad-approved (owner)' => [
            fn () => new AdApprovedMail(
                Ad::factory()->for(User::factory()->agents()->create())->create()->load('user'),
            ),
        ],
    ];
});

it('renders without errors and produces valid HTML', function (Closure $factory): void {
    $mailable = $factory();
    $html = $mailable->render();

    expect($html)
        ->toContain('<!DOCTYPE html')
        ->toContain('</html>')
        ->toContain(config('app.name'))
        ->toContain('<!--[if mso]>');
})->with('mailables');

it('client layout uses pink accent bar', function (): void {
    $html = new VerificationCodeMail('123456', '127.0.0.1', 'now', 'customer')->render();

    expect($html)->toContain('#F6475F');
});

it('owner layout uses teal accent bar', function (): void {
    $html = new VerificationCodeMail('123456', '127.0.0.1', 'now', 'agent')->render();

    expect($html)->toContain('#0d9488');
});
