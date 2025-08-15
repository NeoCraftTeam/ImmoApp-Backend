<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'ad_id' => ['required', 'exists:ad,id'],
            'user_id' => ['required', 'exists:user,id'],
        ];
    }
    public function messages(): array
    {
        return [
            'rating.required' => 'La note est obligatoire.',
            'rating.integer' => 'La note doit être un entier.',
            'rating.between' => 'La note doit être comprise entre 1 et 5.',
            'comment.max' => 'Le commentaire ne doit pas dépasser 1000 caractères.',
            'ad_id.required' => "L'annonce associée est obligatoire.",
            'ad_id.exists' => "L'annonce associée n'existe pas.",
            'user_id.required' => "L'utilisateur est obligatoire.",
            'user_id.exists' => "L'utilisateur sélectionné n'existe pas.",
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
