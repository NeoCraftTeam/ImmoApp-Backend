<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionTier;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property SubscriptionTier $tier
 * @property float $tier_multiplier
 * @property string|null $description
 * @property float $price
 * @property float|null $price_yearly
 * @property int $duration_days
 * @property int $boost_score
 * @property int $boost_duration_days
 * @property int|null $max_ads
 * @property array|null $features
 * @property bool $is_active
 * @property bool $has_trial
 * @property int $trial_days
 * @property bool $has_priority_support
 * @property bool $has_analytics
 * @property bool $has_api_access
 * @property int $sort_order
 */
class SubscriptionPlan extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'tier',
        'tier_multiplier',
        'description',
        'price',
        'price_yearly',
        'duration_days',
        'boost_score',
        'boost_duration_days',
        'max_ads',
        'features',
        'is_active',
        'has_trial',
        'trial_days',
        'has_priority_support',
        'has_analytics',
        'has_api_access',
        'sort_order',
    ];

    protected $casts = [
        'tier' => SubscriptionTier::class,
        'tier_multiplier' => 'float',
        'price' => 'float',
        'price_yearly' => 'float',
        'features' => 'array',
        'is_active' => 'boolean',
        'has_trial' => 'boolean',
        'has_priority_support' => 'boolean',
        'has_analytics' => 'boolean',
        'has_api_access' => 'boolean',
    ];

    /**
     * Get subscriptions using this plan
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Scope to get only active plans
     */
    #[Scope]
    protected function active($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Check if plan has unlimited ads
     */
    public function hasUnlimitedAds(): bool
    {
        return $this->max_ads === null;
    }

    /**
     * Check if plan offers trial period
     */
    public function hasTrial(): bool
    {
        return $this->has_trial && $this->trial_days > 0;
    }

    /**
     * Get yearly price or calculate from monthly
     */
    public function getYearlyPrice(): float
    {
        if ($this->price_yearly) {
            return $this->price_yearly;
        }

        // Calculate yearly with 2 months discount
        return $this->price * 10;
    }

    /**
     * Amount charged for the given billing period, as an integer minor unit.
     * Any period other than "yearly" resolves to the monthly price, matching
     * the subscribe / renew / upgrade flows.
     */
    public function priceForPeriod(string $period): int
    {
        return $period === 'yearly' ? (int) $this->price_yearly : (int) $this->price;
    }

    /**
     * Get monthly savings if paying yearly
     */
    public function getYearlySavings(): float
    {
        $monthlyTotal = $this->price * 12;
        $yearlyPrice = $this->getYearlyPrice();

        return max(0, $monthlyTotal - $yearlyPrice);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format((float) $this->price, 0, ',', ' ').' FCFA';
    }

    /**
     * Get formatted yearly price
     */
    public function getFormattedYearlyPriceAttribute(): string
    {
        return number_format($this->getYearlyPrice(), 0, ',', ' ').' FCFA';
    }

    /**
     * Sync tier-specific features from enum.
     *
     * `tier` is non-nullable on persisted rows (the migration defaults to
     * `basic`), so we don't need a guard clause — the enum cast guarantees
     * a SubscriptionTier instance whenever this method is called.
     */
    public function syncTierFeatures(): void
    {
        $this->features = $this->tier->features();
        $this->tier_multiplier = $this->tier->multiplier();
        $this->max_ads = $this->tier->maxAds();
        $this->boost_duration_days = $this->tier->boostDurationDays();
        $this->has_priority_support = $this->tier->hasPrioritySupport();
        $this->has_analytics = $this->tier->hasAnalytics();
        $this->has_api_access = $this->tier->hasApiAccess();
        $this->sort_order = $this->tier->sortOrder();
    }

    /**
     * Calculate prorated refund amount
     */
    public function calculateProratedRefund(int $daysUsed, int $totalDays): float
    {
        if ($totalDays <= 0 || $daysUsed >= $totalDays) {
            return 0.0;
        }

        $daysRemaining = $totalDays - $daysUsed;
        $dailyRate = $this->price / $totalDays;

        return round($dailyRate * $daysRemaining, 2);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Plan d'abonnement « {$this->name} » {$eventName}");
    }
}
