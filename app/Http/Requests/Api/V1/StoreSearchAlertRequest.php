<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSearchAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:100'],
            'city_id' => ['nullable', 'uuid'],
            'city_name' => ['nullable', 'string', 'max:100'],
            'type_id' => ['nullable', 'uuid'],
            'type_name' => ['nullable', 'string', 'max:100'],
            'quarter_id' => ['nullable', 'uuid'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'bedrooms_min' => ['nullable', 'integer', 'min:0'],
            'surface_min' => ['nullable', 'integer', 'min:0'],
            'has_parking' => ['nullable', 'boolean'],
            'query' => ['nullable', 'string', 'max:200'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_push' => ['nullable', 'boolean'],
        ];
    }
}
