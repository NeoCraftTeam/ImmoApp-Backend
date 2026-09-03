<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Geo;

use Illuminate\Foundation\Http\FormRequest;

class EstimateRentRequest extends FormRequest
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
            'city_id' => ['required', 'uuid'],
            'type_id' => ['required', 'uuid'],
            'surface' => ['required', 'integer', 'min:10', 'max:10000'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];
    }
}
