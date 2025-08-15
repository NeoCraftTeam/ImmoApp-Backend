<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la ville est obligatoire.',
            'name.max' => 'Le nom de la ville ne doit pas dépasser 255 caractères.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
