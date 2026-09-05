<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Mail\AbandonedSearchMail;
use App\Mail\FailedPaymentRetryMail;
use App\Mail\InactivityReminderMail;
use App\Mail\OwnerActivityMail;
use App\Mail\OwnerReEngagementMail;
use App\Mail\OwnerWelcomeDripMail;
use App\Mail\WeeklyDigestMail;
use App\Mail\WelcomeDripMail;
use App\Models\Ad;
use App\Models\AdInteraction;
use App\Models\Conversation;
use App\Models\Payment;
use App\Models\SearchAlert;
use App\Models\User;
use App\Support\EngagementMailGuard;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEngagementEmails extends Command
{
    protected $signature = 'app:send-engagement-emails
                            {--type=all : Type of engagement email to send (all|drip|owner-drip|warm-lead|owner-activity|inactivity|owner-reengagement|failed-payment|digest)}';

    protected $description = 'Send lifecycle engagement emails: client drip D1/3/7, owner drip D1/3/7, D2 warm-lead win-back, D2 owner activity report, client inactivity D7/14/30/60/90, owner re-engagement D7/14/30, failed-payment retries, weekly digest';

    /**
     * Interactions that mean "this person was actually shopping", as opposed to
     * an impression they scrolled past.
     *
     * @var list<string>
     */
    private const array INTENT_TYPES = [
        AdInteraction::TYPE_VIEW,
        AdInteraction::TYPE_FAVORITE,
        AdInteraction::TYPE_SEARCH,
    ];

    public function __construct(private readonly EngagementMailGuard $guard)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->option('type');

        if ($type === 'all' || $type === 'drip') {
            $this->sendWelcomeDrip();
        }

        if ($type === 'all' || $type === 'owner-drip') {
            $this->sendOwnerWelcomeDrip();
        }

        // The two 48h branches run before the D7+ ones: when the weekly ceiling
        // binds, the slot should go to the mail written for someone who was
        // shopping the day before yesterday, not to a generic reminder.
        if ($type === 'all' || $type === 'warm-lead') {
            $this->sendWarmLeadWinBack();
        }

        if ($type === 'all' || $type === 'owner-activity') {
            $this->sendOwnerActivityReports();
        }

        if ($type === 'all' || $type === 'inactivity') {
            $this->sendInactivityReminders();
        }

        if ($type === 'all' || $type === 'owner-reengagement') {
            $this->sendOwnerReEngagement();
        }

        if ($type === 'all' || $type === 'failed-payment') {
            $this->sendFailedPaymentRetries();
        }

        if ($type === 'all' || $type === 'digest') {
            $this->sendWeeklyDigests();
        }

        return self::SUCCESS;
    }

    private function sendWelcomeDrip(): void
    {
        foreach ([1, 3, 7] as $day) {
            $users = User::query()
                ->where('role', UserRole::CUSTOMER)
                ->whereBetween('created_at', [
                    now()->subDays($day + 1),
                    now()->subDays($day),
                ])
                ->get();

            foreach ($users as $user) {
                if (!$this->guard->allows($user, "welcome_drip_{$day}")) {
                    continue;
                }

                try {
                    Mail::to($user->email, $user->firstname)->queue(new WelcomeDripMail($user, $day));
                    $this->guard->record($user, "welcome_drip_{$day}");
                    $this->info("Drip day {$day} queued for {$user->email}");
                } catch (\Throwable $e) {
                    Log::error("WelcomeDrip failed for user {$user->id}", ['error' => $e->getMessage()]);
                }
            }
        }
    }

    private function sendOwnerWelcomeDrip(): void
    {
        foreach ([1, 3, 7] as $day) {
            $owners = User::query()
                ->where('role', UserRole::AGENT)
                ->whereBetween('created_at', [
                    now()->subDays($day + 1),
                    now()->subDays($day),
                ])
                ->get();

            foreach ($owners as $owner) {
                if (!$this->guard->allows($owner, "owner_welcome_drip_{$day}")) {
                    continue;
                }

                try {
                    Mail::to($owner->email, $owner->firstname)->queue(new OwnerWelcomeDripMail($owner, $day));
                    $this->guard->record($owner, "owner_welcome_drip_{$day}");
                    $this->info("Owner drip day {$day} queued for {$owner->email}");
                } catch (\Throwable $e) {
                    Log::error("OwnerWelcomeDrip D{$day} failed for user {$owner->id}", ['error' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * Client win-back two days after browsing stopped.
     *
     * Targeted, not broadcast: the recipient must have viewed, favourited or
     * searched in the last week. Someone who registered and never looked at a
     * single listing has told us nothing, and mailing them is how a domain
     * earns a spam reputation. Someone who opened three flats on Monday and
     * vanished is a warm lead, and the mail can name what they looked at.
     */
    private function sendWarmLeadWinBack(): void
    {
        $intentSince = now()->subDays(7);

        $users = User::query()
            ->where('role', UserRole::CUSTOMER)
            ->whereBetween('last_home_visit_at', [
                now()->subDays(3),
                now()->subDays(2),
            ])
            ->whereHas('adInteractions', fn ($q) => $q
                ->whereIn('type', self::INTENT_TYPES)
                ->where('created_at', '>=', $intentSince))
            ->get();

        foreach ($users as $user) {
            if (!$this->guard->allows($user, 'abandoned_search')) {
                continue;
            }

            try {
                $recentAds = $this->recentlySeenAds($user, $intentSince);

                Mail::to($user->email, $user->firstname)->queue(new AbandonedSearchMail(
                    user: $user,
                    matchingAdsCount: $this->adsPublishedSince($user->last_home_visit_at),
                    searchUrl: rtrim((string) config('app.frontend_url', config('app.url')), '/').'/search',
                    recentAds: $recentAds,
                ));
                $this->guard->record($user, 'abandoned_search');
                $this->info("WarmLead win-back queued for {$user->email}");
            } catch (\Throwable $e) {
                Log::error("AbandonedSearch failed for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * The listings this user opened or favourited, most recent first.
     *
     * @return Collection<int, Ad>
     */
    private function recentlySeenAds(User $user, Carbon $since): Collection
    {
        $adIds = AdInteraction::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [AdInteraction::TYPE_VIEW, AdInteraction::TYPE_FAVORITE])
            ->whereNotNull('ad_id')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->pluck('ad_id')
            ->unique()
            ->take(3);

        if ($adIds->isEmpty()) {
            return Ad::query()->whereRaw('1 = 0')->get();
        }

        // Only what is still on the market: reprinting a withdrawn listing turns
        // a helpful reminder into a dead end.
        return Ad::query()
            ->whereIn('id', $adIds->all())
            ->visible()
            ->publiclyListed()
            ->with('quarter.city')
            ->get();
    }

    private function adsPublishedSince(?Carbon $since): int
    {
        return Ad::query()
            ->visible()
            ->publiclyListed()
            ->where('created_at', '>', $since ?? now()->subDays(3))
            ->count();
    }

    /**
     * Landlord activity report two days after their last sign-in.
     *
     * Only sent when there is demand to report. An owner whose listings drew
     * nothing gets silence here — the D7/D14/D30 re-engagement track already
     * covers "come back and improve your listings", and inventing enthusiasm
     * where there was none teaches the reader to ignore the sender.
     */
    private function sendOwnerActivityReports(): void
    {
        $owners = User::query()
            ->where('role', UserRole::AGENT)
            ->whereBetween('last_seen_at', [
                now()->subDays(3),
                now()->subDays(2),
            ])
            ->get();

        foreach ($owners as $owner) {
            if (!$this->guard->allows($owner, 'owner_activity', cooldownDays: 7)) {
                continue;
            }

            $since = $owner->last_seen_at ?? now()->subDays(3);

            $adIds = Ad::query()->where('user_id', $owner->id)->pluck('id');

            if ($adIds->isEmpty()) {
                continue;
            }

            $viewCount = AdInteraction::query()
                ->whereIn('ad_id', $adIds->all())
                ->where('type', AdInteraction::TYPE_VIEW)
                ->where('created_at', '>=', $since)
                ->count();

            if ($viewCount === 0) {
                continue;
            }

            $favoriteCount = AdInteraction::query()
                ->whereIn('ad_id', $adIds->all())
                ->where('type', AdInteraction::TYPE_FAVORITE)
                ->where('created_at', '>=', $since)
                ->count();

            $unanswered = Conversation::query()
                ->where('landlord_id', $owner->id)
                ->whereNotNull('last_message_at')
                ->where(fn ($q) => $q
                    ->whereNull('landlord_last_read_at')
                    ->orWhereColumn('last_message_at', '>', 'landlord_last_read_at'))
                ->count();

            try {
                $topAds = Ad::query()
                    ->where('user_id', $owner->id)
                    ->visible()
                    ->publiclyListed()
                    ->withCount(['interactions as recent_views' => fn ($q) => $q
                        ->where('type', AdInteraction::TYPE_VIEW)
                        ->where('created_at', '>=', $since)])
                    ->orderByDesc('recent_views')
                    ->with('quarter.city')
                    ->limit(3)
                    ->get();

                Mail::to($owner->email, $owner->firstname)->queue(new OwnerActivityMail(
                    user: $owner,
                    viewCount: $viewCount,
                    favoriteCount: $favoriteCount,
                    unansweredMessages: $unanswered,
                    topAds: $topAds,
                ));
                $this->guard->record($owner, 'owner_activity');
                $this->info("OwnerActivity ({$viewCount} views) queued for {$owner->email}");
            } catch (\Throwable $e) {
                Log::error("OwnerActivity failed for user {$owner->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function sendInactivityReminders(): void
    {
        foreach ([7, 14, 30, 60, 90] as $days) {
            $users = User::query()
                ->where('role', UserRole::CUSTOMER)
                ->where(function ($q) use ($days): void {
                    $q->whereBetween('last_home_visit_at', [
                        now()->subDays($days + 1),
                        now()->subDays($days),
                    ])->orWhere(function ($q2) use ($days): void {
                        $q2->whereNull('last_home_visit_at')
                            ->whereBetween('created_at', [
                                now()->subDays($days + 1),
                                now()->subDays($days),
                            ]);
                    });
                })
                ->get();

            $newAdsCount = Ad::query()
                ->where('created_at', '>', now()->subDays($days))
                ->count();

            foreach ($users as $user) {
                if (!$this->guard->allows($user, "inactivity_{$days}")) {
                    continue;
                }

                try {
                    Mail::to($user->email, $user->firstname)->queue(new InactivityReminderMail($user, $days, $newAdsCount));
                    $this->guard->record($user, "inactivity_{$days}");
                    $this->info("Inactivity ({$days}d) queued for {$user->email}");
                } catch (\Throwable $e) {
                    Log::error("InactivityReminder failed for user {$user->id}", ['error' => $e->getMessage()]);
                }
            }
        }
    }

    private function sendOwnerReEngagement(): void
    {
        foreach ([7, 14, 30] as $days) {
            $owners = User::query()
                ->where('role', UserRole::AGENT)
                ->where(function ($q) use ($days): void {
                    $q->whereBetween('last_seen_at', [
                        now()->subDays($days + 1),
                        now()->subDays($days),
                    ])->orWhere(function ($q2) use ($days): void {
                        $q2->whereNull('last_seen_at')
                            ->whereBetween('created_at', [
                                now()->subDays($days + 1),
                                now()->subDays($days),
                            ]);
                    });
                })
                ->withCount(['ads as active_ads_count' => fn ($q) => $q->whereIn('status', ['available', 'pending'])])
                ->get();

            foreach ($owners as $owner) {
                if (!$this->guard->allows($owner, "owner_reengagement_{$days}")) {
                    continue;
                }

                $activeAdsCount = (int) $owner->getAttribute('active_ads_count');
                $hasPublishedAd = $activeAdsCount > 0
                    || Ad::where('user_id', $owner->id)->exists();

                try {
                    Mail::to($owner->email, $owner->firstname)->queue(
                        new OwnerReEngagementMail(
                            user: $owner,
                            daysSinceActivity: $days,
                            hasPublishedAd: $hasPublishedAd,
                            activeAdsCount: $activeAdsCount,
                        )
                    );
                    $this->guard->record($owner, "owner_reengagement_{$days}");
                    $this->info("OwnerReEngagement ({$days}d) queued for {$owner->email}");
                } catch (\Throwable $e) {
                    Log::error("OwnerReEngagement D{$days} failed for user {$owner->id}", ['error' => $e->getMessage()]);
                }
            }
        }
    }

    private function sendFailedPaymentRetries(): void
    {
        $failedPayments = Payment::query()
            ->where('status', PaymentStatus::FAILED)
            ->whereBetween('updated_at', [
                now()->subHours(25),
                now()->subHours(1),
            ])
            ->with('user')
            ->get();

        foreach ($failedPayments as $payment) {
            /** @var User|null $user */
            $user = $payment->user;

            // A failed payment is something the reader has to act on, so it is
            // exempt from the weekly ceiling — being polite about volume is not
            // worth leaving someone unaware their card was declined.
            if (!$user || !$this->guard->allows($user, 'failed_payment', cooldownDays: 3, respectWeeklyCap: false)) {
                continue;
            }

            try {
                Mail::to($user->email, $user->firstname)->queue(new FailedPaymentRetryMail($payment, $user));
                $this->guard->record($user, 'failed_payment');
                $this->info("FailedPayment retry queued for {$user->email}");
            } catch (\Throwable $e) {
                Log::error("FailedPaymentRetry failed for payment {$payment->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function sendWeeklyDigests(): void
    {
        $users = User::query()
            ->whereHas('searchAlerts', fn ($q) => $q->where('is_active', true))
            ->with('city')
            ->get();

        foreach ($users as $user) {
            // The digest only goes to people holding an active search alert, so
            // it is subscribed mail: the weekly ceiling does not apply to it.
            if (!$this->guard->allows($user, 'weekly_digest', 'digest_emails', cooldownDays: 6, respectWeeklyCap: false)) {
                continue;
            }

            $newAdsCount = Ad::query()
                ->where('created_at', '>', now()->subWeek())
                ->when($user->city_id, fn ($q) => $q->whereHas('quarter.city', fn ($c) => $c->where('city.id', $user->city_id)))
                ->count();

            $matchingAlertsCount = SearchAlert::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->where('last_notified_at', '>', now()->subWeek())
                ->count();

            try {
                Mail::to($user->email, $user->firstname)->queue(new WeeklyDigestMail($user, [
                    'newAdsCount' => $newAdsCount,
                    'matchingAlertsCount' => $matchingAlertsCount,
                    'cityName' => $user->city?->name,
                ]));
                $this->guard->record($user, 'weekly_digest');
                $this->info("WeeklyDigest queued for {$user->email}");
            } catch (\Throwable $e) {
                Log::error("WeeklyDigest failed for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }
}
