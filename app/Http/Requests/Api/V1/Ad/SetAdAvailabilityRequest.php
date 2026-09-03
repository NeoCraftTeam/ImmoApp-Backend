<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ad;

use Illuminate\Foundation\Http\FormRequest;

final class SetAdAvailabilityRequest extends FormRequest
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
            'available_from' => ['nullable', 'date'],
            'available_to' => ['nullable', 'date', 'after_or_equal:available_from'],
        ];
    }
}
