<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Property attributes for ad amenities (Wi-Fi, Parking, Pool, etc.).
 *
 * @property int $id
 * @property int|null $property_attribute_category_id
 * @property string $name
 * @property string $slug
 * @property string $icon
 * @property string $admin_icon
 * @property bool $is_active
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PropertyAttribute extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyAttributeFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'property_attribute_category_id',
        'name',
        'slug',
        'icon',
        'admin_icon',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'property_attribute_category_id' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PropertyAttributeCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyAttributeCategory::class, 'property_attribute_category_id');
    }

    /**
     * Scope: only active attributes.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['property_attribute_category_id', 'name', 'slug', 'icon', 'admin_icon', 'is_active', 'sort_order'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "Attribut « {$this->name} » {$eventName}");
    }

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
     * @return array<string, array<string, string>>
     */
    public static function toGroupedSelectArray(): array
    {
        return self::query()
            ->active()
            ->ordered()
            ->with('category')
            ->get()
            ->groupBy(fn (self $attribute) => optional($attribute->category)->name ?? 'Autres')
            ->map(fn (Collection $group) => $group->pluck('name', 'slug')->all())
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
            ->with('category')
            ->get()
            ->mapWithKeys(fn (self $attr) => [
                $attr->slug => [
                    'value' => $attr->slug,
                    'label' => $attr->name,
                    'icon' => $attr->icon,
                    'admin_icon' => $attr->admin_icon,
                    'category' => [
                        'id' => $attr->category?->id,
                        'name' => $attr->category?->name,
                        'slug' => $attr->category?->slug,
                    ],
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     attributes: array<int, array{
     *         value: string,
     *         label: string,
     *         icon: string,
     *         admin_icon: string
     *     }>
     * }>
     */
    public static function toApiGroupedArray(): array
    {
        $categories = PropertyAttributeCategory::query()
            ->active()
            ->ordered()
            ->with(['propertyAttributes' => function ($query): void {
                $query->active()->ordered();
            }])
            ->get();

        return $categories->map(fn (PropertyAttributeCategory $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'attributes' => $category->propertyAttributes->map(fn (self $attribute) => [
                'value' => $attribute->slug,
                'label' => $attribute->name,
                'icon' => $attribute->icon,
                'admin_icon' => $attribute->admin_icon,
            ])->values()->all(),
        ])->values()->all();
    }
}
