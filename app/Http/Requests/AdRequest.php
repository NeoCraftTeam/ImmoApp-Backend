<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AdStatus;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Http\Requests\TransformsGeojsonGeometry;
use Clickbar\Magellan\Rules\GeometryGeojsonRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property-read array|null $images_to_delete
 */
final class AdRequest extends FormRequest
{
    use TransformsGeojsonGeometry;

    /**
     * Laravel `max` rule for uploaded files is in kilobytes (binary KiB).
     * 20 MiB = 20 * 1024 = 20480.
     */
    private const int MAX_IMAGE_KILOBYTES = 20480;

    private function imageFileRule(bool $sometimes = false): string
    {
        $core = 'image|mimes:jpeg,jpg,png,gif,webp|max:'.self::MAX_IMAGE_KILOBYTES.'|dimensions:max_width=8000,max_height=8000';

        return $sometimes ? 'sometimes|'.$core : $core;
    }

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

                // Filtres numériques
                'bathrooms' => ['nullable', 'integer', 'min:0'],
                'price_min' => ['nullable', 'numeric', 'min:0'],
                'price_max' => ['nullable', 'numeric', 'min:0'],
                'surface_min' => ['nullable', 'numeric', 'min:0'],
                'surface_max' => ['nullable', 'numeric', 'min:0'],
                'has_parking' => ['nullable', 'boolean'],

                // Tri
                'sort' => ['nullable', 'string', 'in:price,surface_area,created_at'],
                'order' => ['nullable', 'string', 'in:asc,desc'],

                // Pagination
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],

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
                // user_id is forced to auth()->id() server-side — not accepted from client
                'quarter_id' => ['required', 'exists:quarter,id'],
                'type_id' => ['required', 'exists:ad_type,id'],

                // Images,   plusieurs formats possibles
                'images' => 'sometimes|array|max:10',
                'images.*' => $this->imageFileRule(false),

                // Alias populaires (acceptation de variations courantes)
                'image' => $this->imageFileRule(true),
                'photos' => 'sometimes|array|max:10',
                'photos.*' => $this->imageFileRule(false),

                // Support pour images[0], images[1], etc.
                'images.0' => $this->imageFileRule(true),
                'images.1' => $this->imageFileRule(true),
                'images.2' => $this->imageFileRule(true),
                'images.3' => $this->imageFileRule(true),
                'images.4' => $this->imageFileRule(true),
                'images.5' => $this->imageFileRule(true),
                'images.6' => $this->imageFileRule(true),
                'images.7' => $this->imageFileRule(true),
                'images.8' => $this->imageFileRule(true),
                'images.9' => $this->imageFileRule(true),
                'attributes' => ['sometimes', 'array'],
                'attributes.*' => [
                    'string',
                    Rule::exists('property_attributes', 'slug')->where(
                        fn ($query) => $query->where('is_active', true)
                    ),
                ],
            ];
        }
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return [
                'title' => ['sometimes', 'string', 'max:255'],
                'slug' => [
                    'sometimes',
                    'string',
                    'max:255',
                    Rule::unique('ad', 'slug')->ignore($this->route('ad')),
                ],
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
                // P2-8 Fix: Allow status updates (validated against enum)
                'status' => ['sometimes', Rule::enum(AdStatus::class)],
                // Task 4 & 5: Visibility and availability management
                'is_visible' => ['sometimes', 'boolean'],
                'available_from' => ['nullable', 'date'],
                'available_to' => ['nullable', 'date', 'after_or_equal:available_from'],
                // Task 6: Property attributes
                'attributes' => ['sometimes', 'array'],
                'attributes.*' => [
                    'string',
                    Rule::exists('property_attributes', 'slug')->where(
                        fn ($query) => $query->where('is_active', true)
                    ),
                ],
                // user_id cannot be changed via API — ownership is immutable
                'quarter_id' => ['sometimes', 'exists:quarter,id'],
                'type_id' => ['sometimes', 'exists:ad_type,id'],

                // Images, plusieurs formats possibles
                'images' => 'sometimes|array|max:10',
                'images.*' => $this->imageFileRule(false),

                'images_to_delete' => 'sometimes|array',
                'images_to_delete.*' => 'exists:media,id',

                // Alias populaires (acceptation de variations courantes)
                'image' => $this->imageFileRule(true),
                'photos' => 'sometimes|array|max:10',
                'photos.*' => $this->imageFileRule(false),

                // Support pour images[0], images[1], etc.
                'images.0' => $this->imageFileRule(true),
                'images.1' => $this->imageFileRule(true),
                'images.2' => $this->imageFileRule(true),
                'images.3' => $this->imageFileRule(true),
                'images.4' => $this->imageFileRule(true),
                'images.5' => $this->imageFileRule(true),
                'images.6' => $this->imageFileRule(true),
                'images.7' => $this->imageFileRule(true),
                'images.8' => $this->imageFileRule(true),
                'images.9' => $this->imageFileRule(true),
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
        $imageMaxMessage = 'Chaque image ne doit pas dépasser 20 Mo.';
        $indexedImageMaxMessages = [];
        foreach (range(0, 9) as $i) {
            $indexedImageMaxMessages["images.{$i}.max"] = $imageMaxMessage;
        }

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

            // Availability validation messages
            'available_to.after_or_equal' => 'La date de fin de disponibilité doit être après ou égale à la date de début.',
            'attributes.*.in' => 'Un ou plusieurs attributs sélectionnés ne sont pas valides.',

            'images.max' => 'Vous pouvez téléverser au maximum 10 images.',
            'images.*.image' => 'Chaque fichier doit être une image.',
            'images.*.mimes' => 'Les images doivent être au format JPEG, PNG, GIF ou WebP.',
            'images.*.max' => $imageMaxMessage,
            'photos.*.image' => 'Chaque fichier doit être une image.',
            'photos.*.mimes' => 'Les images doivent être au format JPEG, PNG, GIF ou WebP.',
            'photos.*.max' => $imageMaxMessage,
            'image.image' => 'Le fichier doit être une image.',
            'image.mimes' => 'L’image doit être au format JPEG, PNG, GIF ou WebP.',
            'image.max' => $imageMaxMessage,
            ...$indexedImageMaxMessages,

        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
