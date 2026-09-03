<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Geo;

use App\Services\Geo\IsochroneService;
use Illuminate\Foundation\Http\FormRequest;

class IsochroneRequest extends FormRequest
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
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'profile' => ['sometimes', 'string', 'in:'.implode(',', IsochroneService::PROFILES)],
            'range' => ['sometimes', 'integer', 'min:5', 'max:60'],
        ];
    }
}
