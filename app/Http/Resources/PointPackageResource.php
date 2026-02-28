<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PointPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PointPackage
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
            'price' => $this->price,
            'price_formatted' => number_format($this->price, 0, ',', ' ').' FCFA',
            'points_awarded' => $this->points_awarded,
            'sort_order' => $this->sort_order,
        ];
    }
}
