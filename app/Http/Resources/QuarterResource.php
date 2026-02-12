<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quarter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Quarter */
final class QuarterResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'name' => $this->name,
            'city_id' => $this->city_id,
            'city_name' => $this->city->name,
        ];
    }
}
