<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AdImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'image_path' => ['required', 'image', 'max:2048'],
            'ad_id' => ['required', 'exists:ad'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'image_path.required' => 'Veillez insérer une image',
            'image_path.image' => 'Le fichier doit être une image valide.',
            'image_path.max' => 'L’image ne doit pas dépasser 2 Mo.',
            'ad_id.required' => 'L’annonce est requis.',
            'ad_id.exists' => "L'annonce sélectionnée n'existe pas.",
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
