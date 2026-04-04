<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Models\EmailPreference;
use App\Models\User;

/**
 * Adds unsubscribe and preference management URLs to marketing emails.
 *
 * Mail classes using this trait must implement `resolveRecipientUser()`.
 */
trait HasUnsubscribeLinks
{
    abstract protected function resolveRecipientUser(): ?User;

    /**
     * The email preference category this mail belongs to.
     * Override in each Mail class to match the EmailPreference column name.
     */
    protected function emailCategory(): string
    {
        return 'all';
    }

    /**
     * @return array{unsubscribeUrl: string|null, preferencesUrl: string|null}
     */
    protected function unsubscribeData(): array
    {
        $user = $this->resolveRecipientUser();

        if (!$user) { // @phpstan-ignore-line booleanNot.alwaysFalse
            return ['unsubscribeUrl' => null, 'preferencesUrl' => null];
        }

        $preference = EmailPreference::getOrCreateForUser($user);
        $token = $preference->unsubscribe_token;
        $category = $this->emailCategory();

        $unsubscribeUrl = route('email.unsubscribe', ['token' => $token])
            .($category !== 'all' ? '?category='.urlencode((string) $category) : '');

        $preferencesUrl = route('email.preferences', ['token' => $token]);

        return [
            'unsubscribeUrl' => $unsubscribeUrl,
            'preferencesUrl' => $preferencesUrl,
        ];
    }

    /**
     * Merge unsubscribe URLs into the Content `with` array.
     *
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    protected function withUnsubscribe(array $with = []): array
    {
        return array_merge($with, $this->unsubscribeData());
    }

    /**
     * Check if the recipient has opted out of this email category.
     */
    protected function recipientOptedOut(): bool
    {
        $user = $this->resolveRecipientUser();

        if (!$user) { // @phpstan-ignore-line booleanNot.alwaysFalse
            return false;
        }

        $preference = EmailPreference::getOrCreateForUser($user);

        return !$preference->isEnabled($this->emailCategory());
    }
}
