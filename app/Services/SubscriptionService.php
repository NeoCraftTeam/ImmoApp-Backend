<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Create a new subscription for an agency
     */
    public function createSubscription(
        Agency $agency,
        SubscriptionPlan $plan,
        string $period = 'monthly',
        ?Payment $payment = null
    ): Subscription {
        return DB::transaction(function () use ($agency, $plan, $payment, $period) {
            // Cancel any existing active subscription
            $this->cancelActiveSubscriptions($agency);

            // Create new subscription
            $subscription = Subscription::create([
                'agency_id' => $agency->id,
                'subscription_plan_id' => $plan->id,
                'billing_period' => $period,
                'status' => SubscriptionStatus::PENDING,
                'payment_id' => $payment?->id,
                'amount_paid' => $payment ? $payment->amount : $plan->price,
                'auto_renew' => false,
            ]);

            return $subscription;
        });
    }

    /**
     * Activate a subscription
     */
    public function activateSubscription(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            $subscription->activate();

            // Boost all existing ads from this agency
            /** @var Agency $agency */
            $agency = $subscription->agency;
            /** @var SubscriptionPlan $plan */
            $plan = $subscription->plan;

            $this->boostAgencyAds($agency, $plan);

            // Envoyer l'email de confirmation
            try {
                foreach ($subscription->agency->users as $user) {
                    \Illuminate\Support\Facades\Mail::to($user->email)
                        ->send(new \App\Mail\SubscriptionSuccessEmail($subscription));
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Erreur envoi mail succès: '.$e->getMessage());
            }
        });
    }

    /**
     * Cancel active subscriptions for an agency
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
     * Check and expire subscriptions
     */
    public function expireSubscriptions(): int
    {
        $expired = Subscription::where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($expired as $subscription) {
            DB::transaction(function () use ($subscription): void {
                $subscription->expire();

                // Remove boost from all agency ads
                $subscription->agency->users->each(function (\App\Models\User $user): void {
                    $user->ads->each(fn (\App\Models\Ad $ad) => $ad->unboost());
                });
            });
        }

        return $expired->count();
    }

    /**
     * Boost all ads from an agency
     */
    protected function boostAgencyAds(Agency $agency, SubscriptionPlan $plan): void
    {
        $agency->users()->get()->each(function ($user) use ($plan): void {
            /** @var \App\Models\User $user */
            $user->ads()
                ->where('status', \App\Enums\AdStatus::AVAILABLE)
                ->get()
                ->each(function ($ad) use ($plan): void {
                    /** @var \App\Models\Ad $ad */
                    $ad->boost($plan->boost_score, $plan->boost_duration_days);
                });
        });
    }

    /**
     * Get subscription statistics for an agency
     */
    public function getAgencyStats(Agency $agency): array
    {
        $currentSubscription = $agency->getCurrentSubscription();

        return [
            'has_active_subscription' => $agency->hasActiveSubscription(),
            'current_plan' => $currentSubscription?->plan->name,
            'days_remaining' => $currentSubscription?->daysRemaining() ?? 0,
            'expires_at' => $currentSubscription?->ends_at,
            'total_boosted_ads' => $agency->users->sum(fn (\App\Models\User $user) => $user->ads()->where('is_boosted', true)->count()),
        ];
    }
}
