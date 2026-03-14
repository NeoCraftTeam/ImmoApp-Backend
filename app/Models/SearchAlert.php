<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $label
 * @property string|null $city_id
 * @property string|null $city_name
 * @property string|null $type_id
 * @property string|null $type_name
 * @property string|null $quarter_id
 * @property int|null $price_min
 * @property int|null $price_max
 * @property int|null $bedrooms_min
 * @property int|null $surface_min
 * @property bool|null $has_parking
 * @property string|null $query
 * @property bool $is_active
 * @property \Carbon\Carbon|null $last_notified_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class SearchAlert extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'label',
        'city_id',
        'city_name',
        'type_id',
        'type_name',
        'quarter_id',
        'price_min',
        'price_max',
        'bedrooms_min',
        'surface_min',
        'has_parking',
        'query',
        'is_active',
        'last_notified_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_parking' => 'boolean',
            'price_min' => 'integer',
            'price_max' => 'integer',
            'bedrooms_min' => 'integer',
            'surface_min' => 'integer',
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Check if a given Ad matches this alert's criteria. */
    public function matchesAd(Ad $ad): bool
    {
        if ($this->city_id && $ad->quarter?->city_id !== $this->city_id) {
            return false;
        }

        if ($this->type_id && $ad->ad_type?->id !== $this->type_id) {
            return false;
        }

        if ($this->quarter_id && $ad->quarter_id !== $this->quarter_id) {
            return false;
        }

        if ($this->price_min !== null && $ad->price !== null && $ad->price < $this->price_min) {
            return false;
        }

        if ($this->price_max !== null && $ad->price !== null && $ad->price > $this->price_max) {
            return false;
        }

        if ($this->bedrooms_min !== null && $ad->bedrooms < $this->bedrooms_min) {
            return false;
        }

        if ($this->surface_min !== null && $ad->surface_area < $this->surface_min) {
            return false;
        }

        if ($this->has_parking !== null && $ad->has_parking !== $this->has_parking) {
            return false;
        }

        if ($this->query) {
            $q = mb_strtolower($this->query);
            $haystack = mb_strtolower($ad->title.' '.$ad->description.' '.$ad->adresse);
            if (!str_contains($haystack, $q)) {
                return false;
            }
        }

        return true;
    }
}
