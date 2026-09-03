<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ad;

use App\Services\Geo\IsochroneService;
use Illuminate\Foundation\Http\FormRequest;

final class AdSearchIsochroneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'max_minutes' => ['sometimes', 'integer', 'min:5', 'max:60'],
            'mode' => ['sometimes', 'string', 'in:'.implode(',', IsochroneService::PROFILES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'transaction_type' => ['sometimes', 'nullable', 'string', 'in:location,vente'],
            'type_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
