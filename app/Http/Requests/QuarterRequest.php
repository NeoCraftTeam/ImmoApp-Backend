<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuarterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:quarters,name'],
            'city_id' => ['required', 'integer' , 'exists:city,id'],
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du quartier est obligatoire.',
            'name.max' => 'Le nom du quartier ne doit pas dépasser 255 caractères.',
            'name.unique' => 'Ce  quartier existe déjà.',
            'city_id.required' => "La ville est obligatoire.",
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
