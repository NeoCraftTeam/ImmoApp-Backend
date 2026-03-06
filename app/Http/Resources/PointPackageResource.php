<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PointPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PointPackage
 */

/**
 * @OA\Schema(
 *     schema="PointPackageResource",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="badge", type="string", nullable=true),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="price_formatted", type="string"),
 *     @OA\Property(property="points_awarded", type="integer"),
 *     @OA\Property(property="features", type="array", @OA\Items(type="string"))
 * )
 */
class PointPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'badge' => $this->badge,
            'price' => $this->price,
            'price_formatted' => number_format($this->price, 0, ',', ' ').' FCFA',
            'points_awarded' => $this->points_awarded,
            'features' => $this->features ?? [],
            'is_popular' => (bool) $this->is_popular,
            'sort_order' => $this->sort_order,
        ];
    }
}
