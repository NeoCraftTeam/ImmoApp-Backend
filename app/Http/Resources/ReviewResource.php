<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */

/**
 * @OA\Schema(
 *     schema="ReviewResource",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="rating", type="integer"),
 *     @OA\Property(property="comment", type="string", nullable=true),
 *     @OA\Property(property="ad_id", type="integer"),
 *     @OA\Property(property="user_id", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
final class ReviewResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'ad_id' => $this->ad_id,
            'user_id' => $this->user_id,

            'ad' => new AdResource($this->whenLoaded('ad')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
