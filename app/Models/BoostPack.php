<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $reach_description
 * @property int $duration_days
 * @property int $boost_score
 * @property int $price_credits
 * @property bool $is_active
 * @property bool $is_popular
 * @property int $sort_order
 */
class BoostPack extends Model
{
    use HasUuids, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'reach_description',
        'duration_days',
        'boost_score',
        'price_credits',
        'is_active',
        'is_popular',
        'sort_order',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'boost_score' => 'integer',
            'price_credits' => 'integer',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<AdBoost, $this> */
    public function adBoosts(): HasMany
    {
        return $this->hasMany(AdBoost::class);
    }

    #[Scope]
    protected function active($query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Pack boost « {$this->name} » {$eventName}");
    }
}
