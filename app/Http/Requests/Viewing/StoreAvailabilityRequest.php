<?php

declare(strict_types=1);

namespace App\Http\Requests\Viewing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by policy in controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['nullable', 'date', 'after:starts_on', 'before:'.now()->addYears(2)->toDateString()],
            'periods' => ['required', 'array', 'min:1', 'max:4'],
            'periods.*.starts_at' => ['required', 'date_format:H:i'],
            'periods.*.ends_at' => ['required', 'date_format:H:i', 'after:periods.*.starts_at'],
            'recurrence' => ['nullable', Rule::in(['once', 'daily', 'weekly', 'biweekly', 'monthly'])],
            'recurrence_days' => ['required_if:recurrence,weekly,biweekly', 'nullable', 'array'],
            'recurrence_days.*' => [Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'days_of_month' => ['required_if:recurrence,monthly', 'nullable', 'array'],
            'days_of_month.*' => ['integer', 'min:1', 'max:31'],
            'slot_duration' => ['nullable', 'integer', 'min:15', 'max:240'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du planning est obligatoire.',
            'starts_on.required' => 'La date de début est obligatoire.',
            'starts_on.after_or_equal' => 'La date de début doit être aujourd\'hui ou dans le futur.',
            'periods.required' => 'Au moins une plage horaire est requise.',
            'periods.*.starts_at.required' => 'L\'heure de début de chaque créneau est obligatoire.',
            'periods.*.ends_at.required' => 'L\'heure de fin de chaque créneau est obligatoire.',
            'periods.*.ends_at.after' => 'L\'heure de fin doit être après l\'heure de début.',
            'recurrence.in' => 'La récurrence doit être : once, daily, weekly, biweekly ou monthly.',
            'slot_duration.min' => 'La durée minimale d\'un créneau est de 15 minutes.',
            'slot_duration.max' => 'La durée maximale d\'un créneau est de 240 minutes.',
        ];
    }
}
