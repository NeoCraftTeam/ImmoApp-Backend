<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Models\User;

/**
 * Sets the mailable locale based on the recipient user's preference.
 *
 * Mail classes using this trait must implement `resolveRecipientUser()`.
 * The trait hooks into `build()` to call `$this->locale()` before rendering.
 */
trait HasLocale
{
    abstract protected function resolveRecipientUser(): ?User;

    /**
     * Apply the recipient's preferred locale to this mailable.
     *
     * Call this in the mailable constructor or before sending.
     */
    protected function applyRecipientLocale(): void
    {
        $user = $this->resolveRecipientUser();
        $fallback = config('app.locale', 'fr');
        $locale = $fallback;
        if ($user !== null && $user->locale !== '') { // @phpstan-ignore-line notIdentical.alwaysTrue
            $locale = $user->locale;
        }

        $this->locale($locale);
    }
}
