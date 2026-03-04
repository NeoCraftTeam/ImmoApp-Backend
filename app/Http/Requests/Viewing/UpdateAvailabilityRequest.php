<?php

declare(strict_types=1);

namespace App\Http\Requests\Viewing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'starts_on' => ['sometimes', 'date', 'after_or_equal:today'],
            'ends_on' => ['sometimes', 'nullable', 'date', 'after:starts_on', 'before:'.now()->addYears(2)->toDateString()],
            'periods' => ['sometimes', 'array', 'min:1', 'max:4'],
            'periods.*.starts_at' => ['required_with:periods', 'date_format:H:i'],
            'periods.*.ends_at' => ['required_with:periods', 'date_format:H:i', 'after:periods.*.starts_at'],
            'recurrence' => ['sometimes', 'nullable', Rule::in(['once', 'daily', 'weekly', 'biweekly', 'monthly'])],
            'recurrence_days' => ['sometimes', 'nullable', 'array'],
            'recurrence_days.*' => [Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'days_of_month' => ['sometimes', 'nullable', 'array'],
            'days_of_month.*' => ['integer', 'min:1', 'max:31'],
            'slot_duration' => ['sometimes', 'integer', 'min:15', 'max:240'],
            'buffer_minutes' => ['sometimes', 'integer', 'min:0', 'max:60'],
        ];
    }
}
