<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuarterRequest extends FormRequest
{
    public function rules(): array
    {
        if($this->isMethod('post')){
            return [
                'name' => ['required', 'string', 'max:255', 'unique:quarter,name'],
                'city_id' => ['required', 'integer', 'exists:city,id'],
            ];
        }

         if($this->isMethod('put') || $this->isMethod('patch')){
            return [
                'name' => ['sometimes', 'string', 'max:255', 'unique:quarter,name'],
                'city_id' => ['sometimes', 'integer', 'exists:city,id'],
            ];
        }
        return [];
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
