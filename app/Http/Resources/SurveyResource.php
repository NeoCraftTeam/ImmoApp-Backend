<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Survey */
final class SurveyResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
            'questions_count' => $this->whenCounted('questions'),
            'questions' => SurveyQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
