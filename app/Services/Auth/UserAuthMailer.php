<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\ForgotPasswordMail;
use App\Mail\VerificationCodeMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Builds and queues the authentication-related transactional emails for a User
 * (password reset, email-verification OTP, admin verification link). Extracted
 * from the User model so the model stays a thin delegator and the mail-building
 * logic lives beside the other App\Services\Auth services.
 */
class UserAuthMailer
{
    public function __construct(private readonly OtpService $otpService) {}

    /**
     * Queue the password reset email using our styled template.
     */
    public function sendPasswordReset(User $user, mixed $token): void
    {
        $resetUrl = config('app.frontend_url').'/reset-password?token='.urlencode((string) $token).'&email='.urlencode($user->email);

        $requestedFrom = request()->ip() ?? 'inconnu';
        $requestedAt = now()->translatedFormat('d F Y à H:i');

        Mail::to($user->email, $user->firstname)
            ->queue(new ForgotPasswordMail($resetUrl, $requestedFrom, $requestedAt, $user->role->value));
    }

    /**
     * Queue a 6-digit OTP code for email verification instead of a magic link.
     *
     * OTP generation and cache management is delegated to {@see OtpService}.
     * A per-user cooldown prevents flooding when the method is called repeatedly.
     */
    public function sendEmailVerification(User $user): void
    {
        if ($this->otpService->isCoolingDown((string) $user->id)) {
            return;
        }

        $otp = $this->otpService->generate((string) $user->id);

        $requestedFrom = request()->ip() ?? 'inconnu';
        $requestedAt = now()->translatedFormat('d F Y à H:i');

        Mail::to($user->email, $user->firstname)
            ->queue(new VerificationCodeMail($otp, $requestedFrom, $requestedAt, $user->role->value));
    }

    /**
     * Queue a verification link by email for admin users (instead of OTP).
     */
    public function sendAdminVerification(User $user): void
    {
        $ttlMinutes = (int) config('auth.verification.expire', 60);

        URL::forceRootUrl(config('app.url'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($ttlMinutes),
            ['id' => $user->getKey(), 'hash' => sha1((string) $user->getEmailForVerification())],
        );

        URL::forceRootUrl(config('app.url'));

        $requestedFrom = request()->ip() ?? 'inconnu';
        $requestedAt = now()->translatedFormat('d F Y à H:i');

        Mail::to($user->email, $user->firstname)
            ->queue(new VerifyEmailMail($verificationUrl, $ttlMinutes, $requestedFrom, $requestedAt));
    }
}
