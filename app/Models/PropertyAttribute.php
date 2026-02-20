<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Property attributes for ad amenities (Wi-Fi, Parking, Pool, etc.).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $icon
 * @property bool $is_active
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PropertyAttribute extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyAttributeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Scope: only active attributes.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: ordered by sort_order then name.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get all active attributes as array for forms.
     *
     * @return array<string, string>
     */
    public static function toSelectArray(): array
    {
        return self::query()
            ->active()
            ->ordered()
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * Get all active attributes with icons for API.
     *
     * @return array<string, array{value: string, label: string, icon: string}>
     */
    public static function toApiArray(): array
    {
        return self::query()
            ->active()
            ->ordered()
            ->get()
            ->mapWithKeys(fn (self $attr) => [
                $attr->slug => [
                    'value' => $attr->slug,
                    'label' => $attr->name,
                    'icon' => $attr->icon,
                ],
            ])
            ->all();
    }
}
