<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PropertyAttribute;
use App\Models\PropertyAttributeCategory;
use App\Support\PropertyAttributeCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PropertyAttributeImportService
{
    /**
     * @return array{categories: int, attributes: int}
     */
    public function import(bool $fresh = false): array
    {
        return DB::transaction(function () use ($fresh): array {
            if ($fresh) {
                PropertyAttribute::query()->delete();
                PropertyAttributeCategory::query()->delete();
            }

            $categoryCount = 0;
            $attributeCount = 0;

            foreach (PropertyAttributeCatalog::categories() as $categoryIndex => $categoryData) {
                $category = PropertyAttributeCategory::query()->updateOrCreate(
                    ['slug' => Str::slug($categoryData['name'])],
                    [
                        'name' => $categoryData['name'],
                        'sort_order' => $categoryIndex + 1,
                        'is_active' => true,
                    ],
                );
                $categoryCount++;

                foreach ($categoryData['attributes'] as $attributeIndex => $attributeData) {
                    PropertyAttribute::query()->updateOrCreate(
                        ['slug' => Str::slug($attributeData['name'])],
                        [
                            'name' => $attributeData['name'],
                            'property_attribute_category_id' => $category->id,
                            'icon' => $attributeData['icon'] ?? $categoryData['icon'],
                            'admin_icon' => $attributeData['admin_icon'] ?? $categoryData['admin_icon'],
                            'sort_order' => (($categoryIndex + 1) * 1000) + $attributeIndex + 1,
                            'is_active' => true,
                        ],
                    );
                    $attributeCount++;
                }
            }

            return [
                'categories' => $categoryCount,
                'attributes' => $attributeCount,
            ];
        });
    }
}
