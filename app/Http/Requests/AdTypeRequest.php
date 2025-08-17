<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'desc' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la ville est obligatoire',
            'name.max' => 'Le nom ne doit pas depasser 255 charactères',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
