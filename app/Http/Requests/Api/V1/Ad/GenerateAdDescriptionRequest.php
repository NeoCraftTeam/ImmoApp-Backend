<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ad;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateAdDescriptionRequest extends FormRequest
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
            'type' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'quarter' => ['nullable', 'string', 'max:100'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'surface' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'transaction_type' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
