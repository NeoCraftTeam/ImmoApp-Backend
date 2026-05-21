<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdStatus;
use App\Enums\PaymentType;
use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionInvoiceMail;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Mail\SubscriptionSuccessEmail;
use App\Models\Ad;
use App\Models\Agency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriptionService
{
    /** Days before expiry to send renewal reminder and generate payment link. */
    private const int RENEWAL_REMINDER_DAYS = 3;

    /** Extra days auto_renew subscriptions stay active past expiry before de-boosting. */
    private const int GRACE_PERIOD_DAYS = 3;

    /**
     * Create a new subscription for an agency.
     */
    public function createSubscription(
        Agency $agency,
        SubscriptionPlan $plan,
        string $period = 'monthly',
        ?Payment $payment = null
    ): Subscription {
        return DB::transaction(
            // P1-4 Fix: Don't cancel active subscription yet!
            // Wait until payment is successful in activateSubscription.
            // $this->cancelActiveSubscriptions($agency);

            fn () => Subscription::create([
                'agency_id' => $agency->id,
                'subscription_plan_id' => $plan->id,
                'billing_period' => $period,
                'status' => SubscriptionStatus::PENDING,
                'payment_id' => $payment?->id,
                'amount_paid' => $payment ? $payment->amount : $plan->price,
                'auto_renew' => false,
            ]));
    }

    /**
     * Activate a subscription, boost ads, generate invoice, and send emails.
     */
    public function activateSubscription(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            // P1-4 Fix: Cancel old subscriptions only when the new one is activated
            $this->cancelActiveSubscriptions($subscription->agency);

            $subscription->activate();

            /** @var Agency $agency */
            $agency = $subscription->agency;
            /** @var SubscriptionPlan $plan */
            $plan = $subscription->plan;

            $this->boostAgencyAds($agency, $plan);

            $invoice = $this->generateInvoice($subscription);

            $this->sendSubscriptionEmails($subscription, $invoice);
        });
    }

    /**
     * Generate an invoice for the subscription.
     */
    public function generateInvoice(Subscription $subscription): Invoice
    {
        return Invoice::create([
            'invoice_number' => Invoice::generateNumber(),
            'subscription_id' => $subscription->id,
            'agency_id' => $subscription->agency_id,
            'payment_id' => $subscription->payment_id,
            'plan_name' => $subscription->plan->name,
            'billing_period' => $subscription->billing_period,
            'amount' => (int) $subscription->amount_paid,
            'currency' => 'XOF',
            'issued_at' => now(),
            'period_start' => $subscription->starts_at,
            'period_end' => $subscription->ends_at,
        ]);
    }

    /**
     * Send subscription confirmation + invoice emails to all agency users.
     */
    protected function sendSubscriptionEmails(Subscription $subscription, Invoice $invoice): void
    {
        try {
            $invoice->load('agency');

            foreach ($subscription->agency->users as $user) {
                /** @var User $user */
                Mail::to($user->email)->send(new SubscriptionSuccessEmail($subscription));
                Mail::to($user->email)->send(new SubscriptionInvoiceMail($user, $invoice));
            }
        } catch (\Exception $e) {
            Log::error('Erreur envoi emails abonnement: '.$e->getMessage());
        }
    }

    /**
     * Cancel active subscriptions for an agency.
     */
    public function cancelActiveSubscriptions(Agency $agency, ?string $reason = null): void
    {
        $agency->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->get()
            ->each(function ($sub) use ($reason): void {
                /** @var Subscription $sub */
                $sub->cancel($reason);
            });
    }

    /**
     * Check and expire subscriptions.
     *
     * Auto-renew subscriptions get a 3-day grace period.
     */
    public function expireSubscriptions(): int
    {
        $expired = Subscription::where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($expired as $subscription) {
            if ($subscription->auto_renew && $subscription->ends_at->diffInDays(now()) < self::GRACE_PERIOD_DAYS) {
                continue;
            }

            DB::transaction(function () use ($subscription): void {
                $subscription->expire();

                $subscription->agency->users->each(function (User $user): void {
                    $user->ads->each(fn (Ad $ad) => $ad->unboost());
                });
            });

            $count++;
        }

        return $count;
    }

    /**
     * Process auto-renewals for subscriptions expiring soon.
     *
     * Generates a payment link and emails agency users 3 days before expiry.
     */
    public function processRenewals(): int
    {
        $subscriptions = Subscription::where('status', SubscriptionStatus::ACTIVE)
            ->where('auto_renew', true)
            ->whereDate('ends_at', '=', now()->addDays(self::RENEWAL_REMINDER_DAYS)->toDateString())
            ->with(['agency.users', 'plan'])
            ->get();

        $count = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $this->sendRenewalReminder($subscription);
                $count++;
            } catch (\Throwable $e) {
                Log::error('Renewal reminder failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Boost all ads from an agency.
     */
    protected function boostAgencyAds(Agency $agency, SubscriptionPlan $plan): void
    {
        $agency->users()->get()->each(function ($user) use ($plan): void {
            /** @var User $user */
            $user->ads()
                ->where('status', AdStatus::AVAILABLE)
                ->get()
                ->each(function ($ad) use ($plan): void {
                    /** @var Ad $ad */
                    $ad->boost($plan->boost_score, $plan->boost_duration_days);
                });
        });
    }

    /**
     * Send a renewal reminder with a pre-filled payment link.
     */
    private function sendRenewalReminder(Subscription $subscription): void
    {
        $agency = $subscription->agency;
        $plan = $subscription->plan;
        $firstUser = $agency->users->first();

        if (!$firstUser || !$plan) {
            return;
        }

        $amount = $subscription->billing_period === 'yearly'
            ? (int) $plan->price_yearly
            : (int) $plan->price;

        $paymentService = app(PaymentService::class);

        $result = $paymentService->createPayment($firstUser, [
            'amount' => (float) $amount,
            'type' => PaymentType::SUBSCRIPTION->value,
            'payment_method' => 'orange_money',
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'period' => $subscription->billing_period,
            'description' => "Renouvellement — {$plan->name} ({$subscription->billing_period})",
            'meta' => [
                'payment_type' => 'subscription',
                'agency_id' => $agency->id,
                'plan_id' => $plan->id,
                'period' => $subscription->billing_period,
                'renewal' => true,
            ],
        ]);

        foreach ($agency->users as $user) {
            try {
                Mail::to($user->email)->send(new SubscriptionRenewalReminderMail(
                    $subscription,
                    $result['link'],
                ));
            } catch (\Throwable $e) {
                Log::error("Renewal email failed for {$user->email}: ".$e->getMessage());
            }
        }

        Log::info('Renewal reminder sent', [
            'subscription_id' => $subscription->id,
            'agency_id' => $agency->id,
            'payment_link' => $result['link'],
        ]);
    }

    /**
     * Get subscription statistics for an agency.
     *
     * @return array{has_active_subscription: bool, current_plan: string|null, days_remaining: int, expires_at: Carbon|null, total_boosted_ads: int}
     */
    public function getAgencyStats(Agency $agency): array
    {
        $currentSubscription = $agency->getCurrentSubscription();

        return [
            'has_active_subscription' => $agency->hasActiveSubscription(),
            'current_plan' => $currentSubscription?->plan->name,
            'days_remaining' => $currentSubscription?->daysRemaining() ?? 0,
            'expires_at' => $currentSubscription?->ends_at,
            'total_boosted_ads' => $agency->users->sum(fn (User $user) => $user->ads()->where('is_boosted', true)->count()),
        ];
    }
}
