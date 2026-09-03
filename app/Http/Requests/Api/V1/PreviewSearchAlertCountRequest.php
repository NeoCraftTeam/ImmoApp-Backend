<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class PreviewSearchAlertCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'city_id' => ['nullable', 'uuid'],
            'city_name' => ['nullable', 'string', 'max:100'],
            'type_id' => ['nullable', 'uuid'],
            'quarter_id' => ['nullable', 'uuid'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'bedrooms_min' => ['nullable', 'integer', 'min:0'],
            'surface_min' => ['nullable', 'integer', 'min:0'],
            'has_parking' => ['nullable', 'boolean'],
        ];
    }
}
