<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EmailPreference;
use App\Models\EmailSendLog;
use App\Models\User;
use App\Providers\AppServiceProvider;

/**
 * Decides whether a lifecycle email may be sent to a user right now.
 *
 * `SendEngagementEmails` runs every morning and has six independent branches.
 * Nothing used to stop them overlapping, so a user who signed up seven days
 * ago, stopped visiting seven days ago and keeps an active search alert could
 * collect the D7 welcome drip, the D7 inactivity reminder and the weekly digest
 * in one morning. Three emails in one minute reads as spam to both the reader
 * and the mailbox provider, and the reputation cost lands on every other mail
 * the platform sends.
 *
 * Two rules, in order of decisiveness:
 *
 * - a cooldown per mail kind, so the same message is not repeated;
 * - a ceiling on all lifecycle mail per user per week.
 *
 * The ceiling covers mail the platform decided to send. Mail the user asked for
 * (the weekly digest, which requires an active search alert) and mail they need
 * to act on (a failed payment) skip the ceiling via `respectWeeklyCap: false` —
 * suppressing those would be the platform failing at its job, not being polite.
 * They still take a slot once sent, so an unsolicited drip gives way to them and
 * not the other way round.
 *
 * Hard bounces and spam complaints are NOT checked here. They are enforced
 * globally on `MessageSending` in {@see AppServiceProvider},
 * which cancels the send whatever its origin — duplicating it here would give
 * two checks free to drift apart.
 */
final class EngagementMailGuard
{
    /**
     * Lifecycle emails one user may receive in a rolling week.
     *
     * Transactional mail (receipts, password resets, viewing confirmations)
     * never passes through this guard and is not counted.
     */
    public const int MAX_PER_WEEK = 3;

    /**
     * Default silence between two sends of the same mail kind.
     */
    public const int DEFAULT_COOLDOWN_DAYS = 14;

    public function allows(
        User $user,
        string $mailKey,
        string $category = 'engagement_emails',
        int $cooldownDays = self::DEFAULT_COOLDOWN_DAYS,
        bool $respectWeeklyCap = true,
    ): bool {
        if (!EmailPreference::getOrCreateForUser($user)->isEnabled($category)) {
            return false;
        }

        $sentLately = EmailSendLog::query()
            ->where('user_id', $user->id)
            ->where('mail_key', $mailKey)
            ->where('sent_at', '>=', now()->subDays($cooldownDays))
            ->exists();

        if ($sentLately) {
            return false;
        }

        if (!$respectWeeklyCap) {
            return true;
        }

        $thisWeek = EmailSendLog::query()
            ->where('user_id', $user->id)
            ->where('sent_at', '>=', now()->subWeek())
            ->count();

        return $thisWeek < self::MAX_PER_WEEK;
    }

    /**
     * Record that a slot was spent. Call this right after queueing the mail.
     */
    public function record(User $user, string $mailKey): void
    {
        EmailSendLog::query()->create([
            'user_id' => $user->id,
            'mail_key' => $mailKey,
            'sent_at' => now(),
        ]);
    }
}
