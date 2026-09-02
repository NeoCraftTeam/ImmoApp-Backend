<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Geo;

use Illuminate\Foundation\Http\FormRequest;

class FindOrCreateQuarterRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'city_id' => ['required', 'uuid', 'exists:city,id'],
        ];
    }
}
