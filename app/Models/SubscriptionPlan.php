<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $price
 * @property float|null $price_yearly
 * @property int $duration_days
 * @property int $boost_score
 * @property int $boost_duration_days
 * @property int|null $max_ads
 * @property array|null $features
 * @property bool $is_active
 * @property int $sort_order
 */
class SubscriptionPlan extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'price_yearly',
        'duration_days',
        'boost_score',
        'boost_duration_days',
        'max_ads',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'float',
        'price_yearly' => 'float',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get subscriptions using this plan
     */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Scope to get only active plans
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
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
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format((float) $this->price, 0, ',', ' ').' FCFA';
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
