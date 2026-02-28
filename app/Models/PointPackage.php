<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property int $price XOF monetary value
 * @property int $points_awarded points credited to the user on purchase
 * @property bool $is_active
 * @property int $sort_order
 */
class PointPackage extends Model
{
    /** @use HasFactory<\Database\Factories\PointPackageFactory> */
    use HasFactory, \Illuminate\Database\Eloquent\Concerns\HasUuids, LogsActivity;

    protected $fillable = [
        'name',
        'price',
        'points_awarded',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'points_awarded' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<PointTransaction, $this> */
    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'payment_id');
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function active($query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, ',', ' ').' FCFA';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Pack de points \u00ab {$this->name} \u00bb {$eventName}");
    }
}
