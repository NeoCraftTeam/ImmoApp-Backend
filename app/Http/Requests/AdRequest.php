<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:ads,slug'], // éviter les doublons
            'description' => ['required', 'string'],
            'adresse' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'surface_area' => ['required', 'numeric', 'min:0'],
            'bedrooms' => ['required', 'integer', 'min:0'],
            'bathrooms' => ['required', 'integer', 'min:0'],
            'has_parking' => ['boolean'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'status' => ['required'], // selon tes statuts
            'expires_at' => ['nullable', 'date'],
            'user_id' => ['required', 'exists:user,id'],
            'quarter_id' => ['required', 'exists:quarter,id'],
            'type_id' => ['required', 'exists:type,id']
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'description.required' => 'La description est obligatoire.',
            'adresse.required' => "L'adresse est obligatoire.",
            'price.required' => 'Le prix est obligatoire.',
            'price.numeric' => 'Le prix doit être un nombre.',
            'user_id.required' => "L'utilisateur est obligatoire.",
            'user_id.exists' => "L'utilisateur sélectionné n'existe pas.",
            'quarter_id.required' => "Le quartier est obligatoire.",
            'quarter_id.exists' => "Le quartier sélectionné n'existe pas.",
            'type_id.required' => "Le type est obligatoire.",
            'type_id.exists' => "Le type sélectionné n'existe pas.",
            'bedrooms.integer' => 'Le nombre de chambres doit être un entier.',
            'bathrooms.integer' => 'Le nombre de salles de bains doit être un entier.',
            'has_parking.boolean' => "Le champ 'parking' doit être vrai ou faux.",
            'status.in' => 'Le statut sélectionné n’est pas valide.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
