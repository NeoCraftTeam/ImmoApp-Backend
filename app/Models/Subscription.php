<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $agency_id
 * @property string $subscription_plan_id
 * @property string|null $previous_plan_id
 * @property string $billing_period
 * @property SubscriptionStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $renewed_at
 * @property string|null $payment_id
 * @property string $amount_paid
 * @property bool $auto_renew
 * @property int $renewal_count
 * @property string|null $cancellation_reason
 * @property-read Agency|null $agency
 * @property-read SubscriptionPlan|null $plan
 * @property-read Payment|null $payment
 */
class Subscription extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'agency_id',
        'subscription_plan_id',
        'previous_plan_id',
        'billing_period',
        'status',
        'starts_at',
        'trial_ends_at',
        'ends_at',
        'cancelled_at',
        'renewed_at',
        'payment_id',
        'amount_paid',
        'auto_renew',
        'renewal_count',
        'cancellation_reason',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'renewed_at' => 'datetime',
            'amount_paid' => 'decimal:2',
            'auto_renew' => 'boolean',
        ];
    }

    /**
     * Get the agency that owns the subscription
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Get the subscription plan
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Get the payment associated with this subscription
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::ACTIVE
            && $this->ends_at?->isFuture() === true;
    }

    /**
     * Check if subscription is on trial
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture()
            && $this->status === SubscriptionStatus::ACTIVE;
    }

    /**
     * Check if trial has ended
     */
    public function trialHasEnded(): bool
    {
        return $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }

    /**
     * Get days remaining in trial
     */
    public function trialDaysRemaining(): int
    {
        if (!$this->isOnTrial()) {
            return 0;
        }

        return (int) max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    /**
     * Check if subscription has expired
     */
    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatus::EXPIRED
            || ($this->ends_at && $this->ends_at->isPast());
    }

    /**
     * Activate the subscription with optional trial
     */
    public function activate(bool $withTrial = false): void
    {
        $duration = $this->billing_period === 'yearly' ? 365 : ($this->plan->duration_days ?? 30);
        $attributes = [
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
        ];

        // If plan has trial and we're activating with trial
        if ($withTrial && $this->plan->has_trial && $this->plan->trial_days > 0) {
            $attributes['trial_ends_at'] = now()->addDays($this->plan->trial_days);
            // Full duration starts after trial
            $attributes['ends_at'] = now()->addDays($this->plan->trial_days + $duration);
        } else {
            $attributes['trial_ends_at'] = null;
            $attributes['ends_at'] = now()->addDays($duration);
        }

        $this->update($attributes);
    }

    /**
     * Convert trial to paid subscription
     */
    public function convertFromTrial(): bool
    {
        if (!$this->isOnTrial()) {
            return false;
        }

        $this->update([
            'trial_ends_at' => now(), // Mark trial as ended
        ]);

        return true;
    }

    /**
     * Renew the subscription
     */
    public function renew(): void
    {
        $duration = $this->billing_period === 'yearly' ? 365 : ($this->plan->duration_days ?? 30);

        $this->update([
            'status' => SubscriptionStatus::ACTIVE,
            'renewed_at' => now(),
            'renewal_count' => $this->renewal_count + 1,
            'ends_at' => now()->addDays($duration),
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    /**
     * Upgrade to a different plan
     */
    public function upgradeTo(SubscriptionPlan $newPlan, string $billingPeriod = 'monthly'): void
    {
        $duration = $billingPeriod === 'yearly' ? 365 : ($newPlan->duration_days ?? 30);

        $this->update([
            'previous_plan_id' => $this->subscription_plan_id,
            'subscription_plan_id' => $newPlan->id,
            'billing_period' => $billingPeriod,
            'ends_at' => now()->addDays($duration),
        ]);
    }

    /**
     * Downgrade to a different plan
     */
    public function downgradeTo(SubscriptionPlan $newPlan, string $billingPeriod = 'monthly'): void
    {
        // Downgrade applies at end of current billing period
        $this->update([
            'previous_plan_id' => $this->subscription_plan_id,
            'subscription_plan_id' => $newPlan->id,
            'billing_period' => $billingPeriod,
            // Keep current ends_at - change takes effect on next renewal
        ]);
    }

    /**
     * Cancel the subscription
     */
    public function cancel(?string $reason = null, bool $immediate = false): void
    {
        $attributes = [
            'auto_renew' => false,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ];

        // Grace by default: keep status ACTIVE so paid access runs until `ends_at`
        // (the expiry sweep flips it once `ends_at` passes). Immediate revocation is
        // reserved for refunds and plan replacements, where access must stop now.
        if ($immediate) {
            $attributes['status'] = SubscriptionStatus::CANCELLED;
        }

        $this->update($attributes);
    }

    /**
     * Mark subscription as expired
     */
    public function expire(): void
    {
        $this->update([
            'status' => SubscriptionStatus::EXPIRED,
        ]);
    }

    /**
     * Get days remaining
     */
    public function daysRemaining(): int
    {
        if ($this->ends_at === null) {
            return 0;
        }

        return (int) max(0, now()->diffInDays($this->ends_at, false));
    }

    /**
     * Scope to get active subscriptions
     */
    #[Scope]
    protected function active($query)
    {
        return $query->where('status', SubscriptionStatus::ACTIVE)
            ->where('ends_at', '>', now());
    }

    /**
     * Scope to get expired subscriptions
     */
    #[Scope]
    protected function expired($query)
    {
        return $query->where(function ($q): void {
            $q->where('status', SubscriptionStatus::EXPIRED)
                ->orWhere('ends_at', '<=', now());
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Abonnement #{$this->id} {$eventName}");
    }
}
