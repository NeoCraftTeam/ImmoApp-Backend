<?php

namespace App\Http\Requests;

use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Http\Requests\TransformsGeojsonGeometry;
use Clickbar\Magellan\Rules\GeometryGeojsonRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read array|null $images_to_delete
 */
class AdRequest extends FormRequest
{
    use TransformsGeojsonGeometry;

    public function rules(): array
    {

        // Règles pour la recherche (GET)
        if ($this->isMethod('get')) {
            return [
                // Recherche textuelle
                'q' => ['nullable', 'string', 'max:255'],

                // Filtres
                'city' => ['nullable', 'string', 'max:100'],
                'type' => ['nullable', 'string', 'max:100'],
                'bedrooms' => ['nullable', 'integer', 'min:0'],
                'quarter_id' => ['sometimes', 'exists:quarter,id'],
                'type_id' => ['sometimes', 'exists:ad_type,id'],

                // Tri
                'sort' => ['nullable', 'string', 'in:price,surface_area,created_at'],
                'order' => ['nullable', 'string', 'in:asc,desc'],

                // Pagination
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],

                // Autocomplete
                'field' => ['nullable', 'string', 'in:city,type,quarter'],

                // Nearby
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'radius' => 'nullable|numeric|min:0',
            ];
        }

        if ($this->isMethod('post')) {
            return [
                'title' => ['required', 'string', 'max:255'],
                'slug' => ['string', 'max:255', 'unique:ad,slug'], // éviter les doublons
                'description' => ['required', 'string'],
                'adresse' => ['required', 'string', 'max:255'],
                'price' => ['required', 'numeric', 'min:0'],
                'surface_area' => ['required', 'numeric', 'min:0'],
                'bedrooms' => ['required', 'integer', 'min:0'],
                'bathrooms' => ['required', 'integer', 'min:0'],
                'has_parking' => ['required', 'string'],
                'location' => [new GeometryGeojsonRule([Point::class])],
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'nullable|numeric|min:0',
                'expires_at' => ['nullable', 'date'],
                'user_id' => ['required', 'exists:users,id'],
                'quarter_id' => ['required', 'exists:quarter,id'],
                'type_id' => ['required', 'exists:ad_type,id'],

                // Images,   plusieurs formats possibles
                'images' => 'sometimes|array|max:10',
                'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max

                // Alias populaires (acceptation de variations courantes)
                'image' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'photos' => 'sometimes|array|max:10',
                'photos.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120',

                // Support pour images[0], images[1], etc.
                'images.0' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.1' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.2' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.3' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.4' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.5' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.6' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.7' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.8' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.9' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            ];
        }
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return [
                'title' => ['sometimes', 'string', 'max:255'],
                'slug' => ['string', 'max:255', 'unique:ad,slug'], // éviter les doublons
                'description' => ['sometimes', 'string'],
                'adresse' => ['sometimes', 'string', 'max:255'],
                'price' => ['sometimes', 'numeric', 'min:0'],
                'surface_area' => ['sometimes', 'numeric', 'min:0'],
                'bedrooms' => ['sometimes', 'integer', 'min:0'],
                'bathrooms' => ['sometimes', 'integer', 'min:0'],
                'has_parking' => ['sometimes', 'string'],
                'location' => [new GeometryGeojsonRule([Point::class])],
                'latitude' => 'sometimes|numeric|between:-90,90',
                'longitude' => 'sometimes|numeric|between:-180,180',
                'expires_at' => ['nullable', 'date'],
                'user_id' => ['sometimes', 'exists:users,id'],
                'quarter_id' => ['sometimes', 'exists:quarter,id'],
                'type_id' => ['sometimes', 'exists:ad_type,id'],

                // Images, plusieurs formats possibles
                'images' => 'sometimes|array|max:10',
                'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max

                'images_to_delete' => 'sometimes|array',
                'images_to_delete.*' => 'exists:media,id',

                // Alias populaires (acceptation de variations courantes)
                'image' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'photos' => 'sometimes|array|max:10',
                'photos.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:5120',

                // Support pour images[0], images[1], etc.
                'images.0' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.1' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.2' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.3' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.4' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.5' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.6' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.7' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.8' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'images.9' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            ];
        }

        return [];
    }

    public function geometries(): array
    {
        return ['location'];
    }

    #[\Override]
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
            'quarter_id.required' => 'Le quartier est obligatoire.',
            'quarter_id.exists' => "Le quartier sélectionné n'existe pas.",
            'type_id.required' => 'Le type est obligatoire.',
            'type_id.exists' => "Le type sélectionné n'existe pas.",
            'bedrooms.integer' => 'Le nombre de chambres doit être un entier.',
            'bathrooms.integer' => 'Le nombre de salles de bains doit être un entier.',

            'images.max' => 'You can upload a maximum of 10 images.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.mimes' => 'Images must be in JPEG, PNG, GIF, or WebP format.',
            'images.*.max' => 'Each image must not exceed 5MB.',

        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
