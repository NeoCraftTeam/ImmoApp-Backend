<?php

declare(strict_types=1);

namespace App\Http\Requests\Viewing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationNotesRequest extends FormRequest
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
            'landlord_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
