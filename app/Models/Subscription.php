<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $agency_id
 * @property string $subscription_plan_id
 * @property string $billing_period
 * @property SubscriptionStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $cancelled_at
 * @property string|null $payment_id
 * @property string $amount_paid
 * @property bool $auto_renew
 * @property string|null $cancellation_reason
 * @property-read Agency|null $agency
 * @property-read SubscriptionPlan|null $plan
 * @property-read Payment|null $payment
 */
class Subscription extends Model
{
    use HasUuids, LogsActivity;

    protected $fillable = [
        'agency_id',
        'subscription_plan_id',
        'billing_period',
        'status',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'payment_id',
        'amount_paid',
        'auto_renew',
        'cancellation_reason',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'amount_paid' => 'decimal:2',
        'auto_renew' => 'boolean',
    ];

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
     * Check if subscription has expired
     */
    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatus::EXPIRED
            || ($this->ends_at && $this->ends_at->isPast());
    }

    /**
     * Activate the subscription
     */
    public function activate(): void
    {
        $duration = $this->billing_period === 'yearly' ? 365 : ($this->plan->duration_days ?? 30);

        $this->update([
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays($duration),
        ]);
    }

    /**
     * Cancel the subscription
     */
    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
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
