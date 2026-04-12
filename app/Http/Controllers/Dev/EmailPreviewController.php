<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Enums\UserRole;
use App\Mail\AccountDeletedMail;
use App\Mail\ForgotPasswordMail;
use App\Mail\ResetPasswordMail;
use App\Mail\VerificationCodeMail;
use App\Mail\WelcomeDripMail;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

/**
 * Dev-only email template preview controller.
 *
 * Renders Mailable classes directly in the browser so designers
 * and developers can verify layout, branding, and responsiveness
 * without actually sending emails.
 *
 * Guarded by APP_ENV=local — returns 404 in production.
 */
final class EmailPreviewController
{
    /**
     * Catalog: list all previewable email templates.
     */
    public function index(): Response
    {
        $templates = $this->getTemplates();

        $html = '<html><head><meta charset="UTF-8"><title>Email Previews — KeyHome Dev</title>';
        $html .= '<style>body{font-family:system-ui,sans-serif;max-width:800px;margin:40px auto;padding:0 20px;color:#1a1a2e}';
        $html .= 'h1{color:#F6475F}a{color:#0D9488;text-decoration:none}a:hover{text-decoration:underline}';
        $html .= '.card{border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;margin:8px 0;display:flex;justify-content:space-between;align-items:center}';
        $html .= '.badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:600}';
        $html .= '.badge-pink{background:#fce7ea;color:#F6475F}.badge-teal{background:#e0f7f5;color:#0D9488}';
        $html .= '</style></head><body>';
        $html .= '<h1>📧 Email Template Previews</h1>';
        $html .= '<p style="color:#64748b">'.count($templates).' templates available. Click to preview in browser.</p>';

        foreach ($templates as $t) {
            $badge = $t['layout'] === 'owner'
                ? '<span class="badge badge-teal">owner</span>'
                : '<span class="badge badge-pink">client</span>';
            $html .= '<div class="card"><div><a href="/dev/email-preview/'.$t['slug'].'">'.$t['name'].'</a></div>'.$badge.'</div>';
        }

        $html .= '</body></html>';

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Render a single email template by slug.
     */
    public function show(string $slug): Response
    {
        $mailable = $this->resolveMailable($slug);

        if (!$mailable) {
            abort(404, "Email template '{$slug}' not found.");
        }

        return new Response($mailable->render(), 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Build a fake user for preview data.
     */
    private function fakeUser(): User
    {
        $user = new User;
        $user->id = '00000000-0000-0000-0000-000000000001';
        $user->firstname = 'Jean';
        $user->lastname = 'Dupont';
        $user->email = 'jean@example.com';
        $user->role = UserRole::CUSTOMER;

        return $user;
    }

    /**
     * @return Collection<int, array{slug: string, name: string, layout: string}>
     */
    private function getTemplates(): Collection
    {
        return collect([
            ['slug' => 'verification-code', 'name' => 'Code de vérification (OTP)', 'layout' => 'client'],
            ['slug' => 'verification-code-owner', 'name' => 'Code de vérification (OTP) — Owner', 'layout' => 'owner'],
            ['slug' => 'reset-password', 'name' => 'Réinitialisation mot de passe', 'layout' => 'client'],
            ['slug' => 'reset-password-owner', 'name' => 'Réinitialisation mot de passe — Owner', 'layout' => 'owner'],
            ['slug' => 'forgot-password', 'name' => 'Mot de passe oublié', 'layout' => 'client'],
            ['slug' => 'forgot-password-owner', 'name' => 'Mot de passe oublié — Owner', 'layout' => 'owner'],
            ['slug' => 'account-deleted', 'name' => 'Compte supprimé (Client)', 'layout' => 'client'],
            ['slug' => 'account-deleted-owner', 'name' => 'Compte supprimé (Owner)', 'layout' => 'owner'],
            ['slug' => 'welcome-drip-1', 'name' => 'Bienvenue — Jour 1', 'layout' => 'client'],
            ['slug' => 'welcome-drip-3', 'name' => 'Bienvenue — Jour 3', 'layout' => 'client'],
            ['slug' => 'welcome-drip-7', 'name' => 'Bienvenue — Jour 7', 'layout' => 'client'],
        ]);
    }

    /**
     * Resolve a Mailable instance from a slug, with fake data.
     */
    private function resolveMailable(string $slug): ?Mailable
    {
        $now = now()->translatedFormat('d F Y à H:i');
        $ip = '127.0.0.1';

        return match ($slug) {
            'verification-code' => new VerificationCodeMail('123456', $ip, $now, 'customer'),
            'verification-code-owner' => new VerificationCodeMail('123456', $ip, $now, 'agent'),
            'reset-password' => new ResetPasswordMail('654321', $ip, $now, 'customer'),
            'reset-password-owner' => new ResetPasswordMail('654321', $ip, $now, 'agent'),
            'forgot-password' => new ForgotPasswordMail('https://keyhome.app/reset-password?token=fake', $ip, $now, 'customer'),
            'forgot-password-owner' => new ForgotPasswordMail('https://keyhome.app/reset-password?token=fake', $ip, $now, 'agent'),
            'account-deleted' => new AccountDeletedMail('Jean Dupont', 'jean@example.com', UserRole::CUSTOMER),
            'account-deleted-owner' => new AccountDeletedMail('Jean Dupont', 'jean@example.com', UserRole::AGENT),
            'welcome-drip-1' => new WelcomeDripMail($this->fakeUser(), 1),
            'welcome-drip-3' => new WelcomeDripMail($this->fakeUser(), 3),
            'welcome-drip-7' => new WelcomeDripMail($this->fakeUser(), 7),
            default => null,
        };
    }
}
