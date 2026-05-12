<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SurveyQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SurveyQuestion */
final class SurveyQuestionResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'type' => $this->type,
            'options' => $this->options,
            'order' => $this->order,
        ];
    }
}
