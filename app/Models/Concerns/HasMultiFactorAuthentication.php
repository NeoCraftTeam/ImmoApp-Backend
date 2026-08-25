<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;

/**
 * Multi-factor authentication storage for the User model: the TOTP-app secret,
 * recovery codes, and the email-OTP toggle. Implements the getters/setters
 * required by Filament's {@see HasAppAuthentication},
 * {@see HasAppAuthenticationRecovery}
 * and {@see HasEmailAuthentication}
 * contracts. Backed by encrypted casts on the model.
 *
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 * @property bool $has_email_authentication
 * @property string $email
 */
trait HasMultiFactorAuthentication
{
    /** {@inheritDoc} */
    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    /** {@inheritDoc} */
    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    /**
     * Return the account label shown inside the user's authenticator app.
     *
     * Using the email address ensures uniqueness across multiple accounts.
     */
    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string>|null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string>|null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    /** {@inheritDoc} */
    public function hasEmailAuthentication(): bool
    {
        return (bool) $this->has_email_authentication;
    }

    /** {@inheritDoc} */
    public function toggleEmailAuthentication(bool $condition): void
    {
        $this->has_email_authentication = $condition;
        $this->save();
    }
}
