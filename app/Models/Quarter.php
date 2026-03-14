<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuarterFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property string $city_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \App\Models\City $city
 *
 * @method static QuarterFactory factory($count = null, $state = [])
 * @method static Builder<static>|Quarter newModelQuery()
 * @method static Builder<static>|Quarter newQuery()
 * @method static Builder<static>|Quarter onlyTrashed()
 * @method static Builder<static>|Quarter query()
 * @method static Builder<static>|Quarter whereCityId($value)
 * @method static Builder<static>|Quarter whereCreatedAt($value)
 * @method static Builder<static>|Quarter whereDeletedAt($value)
 * @method static Builder<static>|Quarter whereId($value)
 * @method static Builder<static>|Quarter whereName($value)
 * @method static Builder<static>|Quarter whereUpdatedAt($value)
 * @method static Builder<static>|Quarter withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Quarter withoutTrashed()
 *
 * @mixin Eloquent
 */
class Quarter extends Model
{
    use HasFactory, HasUuids, LogsActivity, softDeletes;

    protected $table = 'quarter';

    protected $fillable = [
        'name',
        'city_id',
        'avg_price',
        'avg_price_per_sqm',
        'active_ads_count',
        'pricing_updated_at',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<Ad, $this> */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'avg_price' => 'decimal:2',
            'avg_price_per_sqm' => 'decimal:2',
            'pricing_updated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'city_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Quartier « {$this->name} » {$eventName}");
    }
}
