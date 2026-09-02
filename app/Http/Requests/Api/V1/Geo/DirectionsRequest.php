<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Geo;

use App\Services\Geo\DirectionsService;
use Illuminate\Foundation\Http\FormRequest;

class DirectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],
            'to_lat' => ['required', 'numeric', 'between:-90,90'],
            'to_lng' => ['required', 'numeric', 'between:-180,180'],
            'profile' => ['sometimes', 'string', 'in:'.implode(',', DirectionsService::PROFILES)],
        ];
    }
}
