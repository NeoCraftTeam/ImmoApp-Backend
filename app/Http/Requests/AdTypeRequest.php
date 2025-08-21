<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdTypeRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('post')) {
            // Rules for creattion
            return [
                'name' => ['required', 'string', 'max:255'],
                'desc' => ['nullable', 'string'],
            ];
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            // Rules for updates
            return [
                'name' => ['sometimes', 'string', 'max:255'], // sometime is to set the field to optionnal
                'desc' => ['sometimes', 'string'],
            ];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire lors de la création',
            'name.max' => 'Le nom ne doit pas dépasser 255 caractères',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
