<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Mail\FailedPaymentRetryMail;
use App\Mail\InactivityReminderMail;
use App\Mail\WeeklyDigestMail;
use App\Mail\WelcomeDripMail;
use App\Models\Ad;
use App\Models\EmailPreference;
use App\Models\Payment;
use App\Models\SearchAlert;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEngagementEmails extends Command
{
    protected $signature = 'app:send-engagement-emails
                            {--type=all : Type of engagement email to send (all|drip|inactivity|failed-payment|digest)}';

    protected $description = 'Send engagement emails: welcome drip series, inactivity reminders, failed payment retries, weekly digest';

    public function handle(): int
    {
        $type = $this->option('type');

        if ($type === 'all' || $type === 'drip') {
            $this->sendWelcomeDrip();
        }

        if ($type === 'all' || $type === 'inactivity') {
            $this->sendInactivityReminders();
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
                ->whereBetween('created_at', [
                    now()->subDays($day + 1),
                    now()->subDays($day),
                ])
                ->get();

            foreach ($users as $user) {
                if (!$this->isEligible($user, 'engagement_emails')) {
                    continue;
                }

                try {
                    Mail::to($user->email, $user->firstname)->queue(new WelcomeDripMail($user, $day));
                    $this->info("Drip day {$day} queued for {$user->email}");
                } catch (\Throwable $e) {
                    Log::error("WelcomeDrip failed for user {$user->id}", ['error' => $e->getMessage()]);
                }
            }
        }
    }

    private function sendInactivityReminders(): void
    {
        foreach ([30, 60, 90] as $days) {
            $users = User::query()
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
                if (!$this->isEligible($user, 'engagement_emails')) {
                    continue;
                }

                try {
                    Mail::to($user->email, $user->firstname)->queue(new InactivityReminderMail($user, $days, $newAdsCount));
                    $this->info("Inactivity ({$days}d) queued for {$user->email}");
                } catch (\Throwable $e) {
                    Log::error("InactivityReminder failed for user {$user->id}", ['error' => $e->getMessage()]);
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

            if (!$user || !$this->isEligible($user, 'engagement_emails')) {
                continue;
            }

            try {
                Mail::to($user->email, $user->firstname)->queue(new FailedPaymentRetryMail($payment, $user));
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
            if (!$this->isEligible($user, 'digest_emails')) {
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
                $this->info("WeeklyDigest queued for {$user->email}");
            } catch (\Throwable $e) {
                Log::error("WeeklyDigest failed for user {$user->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function isEligible(User $user, string $category): bool
    {
        $preference = EmailPreference::getOrCreateForUser($user);

        return $preference->isEnabled($category);
    }
}
