<?php

declare(strict_types=1);

namespace App\Http\Requests\Viewing;

use Illuminate\Foundation\Http\FormRequest;

class StoreTentativeReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slot_date' => ['required', 'date', 'after_or_equal:today'],
            'slot_starts_at' => ['required', 'date_format:H:i'],
            'slot_ends_at' => ['required', 'date_format:H:i', 'after:slot_starts_at'],
            'client_message' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'slot_date.required' => 'La date du créneau est obligatoire.',
            'slot_date.after_or_equal' => 'Vous ne pouvez pas réserver un créneau passé.',
            'slot_starts_at.required' => 'L\'heure de début est obligatoire.',
            'slot_starts_at.date_format' => 'L\'heure de début doit être au format HH:MM.',
            'slot_ends_at.required' => 'L\'heure de fin est obligatoire.',
            'slot_ends_at.after' => 'L\'heure de fin doit être après l\'heure de début.',
            'client_message.max' => 'Le message ne peut pas dépasser 500 caractères.',
        ];
    }
}
