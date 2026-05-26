<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AdStatus;
use App\Rules\VerifiedImageUpload;
use App\Rules\VerifiedPdfUpload;
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

    /**
     * @return array<int, mixed>
     */
    private function imageFileRules(bool $sometimes = false): array
    {
        $rules = [
            'image',
            'mimes:jpeg,jpg,png,gif,webp',
            'mimetypes:image/jpeg,image/png,image/gif,image/webp',
            'max:'.self::MAX_IMAGE_KILOBYTES,
            'dimensions:max_width=8000,max_height=8000',
            new VerifiedImageUpload,
        ];

        if ($sometimes) {
            array_unshift($rules, 'sometimes');
        }

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    private function propertyConditionPdfRules(): array
    {
        return [
            'nullable',
            'file',
            'mimes:pdf',
            'mimetypes:application/pdf',
            'max:10240',
            new VerifiedPdfUpload,
        ];
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
                'country' => ['nullable', 'string', 'max:100'],
                'type' => ['nullable', 'string', 'max:100'],
                'quarter' => ['nullable', 'string', 'max:100'],
                'transaction_type' => ['nullable', 'string', 'in:location,vente'],
                'price_period' => ['nullable', 'string', 'in:mois,jour'],
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
                'has_3d_tour' => ['nullable', 'boolean'],
                'is_verified' => ['nullable', 'boolean'],
                'attributes' => ['nullable', 'array', 'max:20'],
                'attributes.*' => ['string', 'max:100'],

                // Tri
                'sort' => ['nullable', 'string', 'in:price,surface_area,created_at,boost_score,reviews_avg_rating,views_count,_geoPoint,newest,price_asc,price_desc'],
                'order' => ['nullable', 'string', 'in:asc,desc'],

                // Pagination
                'page' => ['nullable', 'integer', 'min:1'],
                // Controllers clamp per_page (index ≤100, feed ≤50); cap prevents abuse.
                'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
                'cursor' => ['nullable', 'string', 'max:2048'],

                // Exclude specific ads (DoS guard: cap at 50 UUIDs)
                'exclude_ids' => ['sometimes', 'array', 'max:50'],
                'exclude_ids.*' => ['string', 'uuid'],

                // Autocomplete
                'field' => ['nullable', 'string', 'in:city,type,quarter'],

                // Nearby
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'radius' => 'nullable|numeric|min:0',
            ];
        }

        if ($this->isMethod('post')) {
            $isDraft = $this->boolean('is_draft');

            // When saving as draft, only title is required — all other fields become optional.
            $req = $isDraft ? 'sometimes' : 'required';

            $rules = [
                'is_draft' => ['sometimes', 'boolean'],
                'title' => ['required', 'string', 'max:255'],
                'transaction_type' => ['nullable', 'string', 'in:location,vente'],
                'slug' => ['string', 'max:255', 'unique:ad,slug'],
                'description' => [$req, 'string'],
                'adresse' => [$req, 'string', 'max:255'],
                'price' => [$req, 'numeric', 'min:0'],
                'price_period' => ['nullable', 'string', 'in:mois,jour'],
                'surface_area' => [$req, 'numeric', 'min:0'],
                'bedrooms' => [$req, 'integer', 'min:0'],
                'bathrooms' => [$req, 'integer', 'min:0'],
                'has_parking' => [$req, 'string'],
                'location' => [new GeometryGeojsonRule([Point::class])],
                'latitude' => ($isDraft ? 'sometimes' : 'required').'|numeric|between:-90,90',
                'longitude' => ($isDraft ? 'sometimes' : 'required').'|numeric|between:-180,180',
                'radius' => 'nullable|numeric|min:0',
                'expires_at' => ['nullable', 'date'],
                // user_id is forced to auth()->id() server-side — not accepted from client
                'quarter_id' => [$req, 'exists:quarter,id'],
                'type_id' => [$req, 'exists:ad_type,id'],

                // Images,   plusieurs formats possibles
                'images' => 'sometimes|array|max:10',
                'images.*' => $this->imageFileRules(false),

                // Alias populaires (acceptation de variations courantes)
                'image' => $this->imageFileRules(true),
                'photos' => 'sometimes|array|max:10',
                'photos.*' => $this->imageFileRules(false),

                // Support pour images[0], images[1], etc.
                'images.0' => $this->imageFileRules(true),
                'images.1' => $this->imageFileRules(true),
                'images.2' => $this->imageFileRules(true),
                'images.3' => $this->imageFileRules(true),
                'images.4' => $this->imageFileRules(true),
                'images.5' => $this->imageFileRules(true),
                'images.6' => $this->imageFileRules(true),
                'images.7' => $this->imageFileRules(true),
                'images.8' => $this->imageFileRules(true),
                'images.9' => $this->imageFileRules(true),
                'attributes' => ['sometimes', 'array'],
                'attributes.*' => [
                    'string',
                    Rule::exists('property_attributes', 'slug')->where(
                        fn ($query) => $query->where('is_active', true)
                    ),
                ],

                // Premium lease conditions
                'deposit_amount' => ['nullable', 'string', 'max:50'],
                'minimum_lease_duration' => ['nullable', 'string', 'max:50'],

                // Charges
                'charges_forfaitaires' => ['nullable', 'boolean'],
                'charges_montant_forfait' => ['nullable', 'numeric', 'min:0'],
                'charges_eau' => ['nullable', 'numeric', 'min:0'],
                'charges_electricite' => ['nullable', 'numeric', 'min:0'],
                'charges_autres' => ['nullable', 'string', 'max:500'],

                // Proximity distances (metres)
                'distance_main_road_m' => ['nullable', 'integer', 'min:0', 'max:99999'],
                'distance_shops_m' => ['nullable', 'integer', 'min:0', 'max:99999'],
                'distance_transport_m' => ['nullable', 'integer', 'min:0', 'max:99999'],
                'distance_school_m' => ['nullable', 'integer', 'min:0', 'max:99999'],
                'distance_hospital_m' => ['nullable', 'integer', 'min:0', 'max:99999'],

                // Property condition PDF
                'property_condition' => $this->propertyConditionPdfRules(),

                // Boost request
                'is_boost_requested' => ['nullable', 'boolean'],

                // Idempotency (ignored by validated(), handled before)
                '_idempotency_key' => ['nullable', 'string', 'max:128'],
            ];

            // For drafts, also make nullable the fields that normally require a value
            if ($isDraft) {
                $draftNullable = ['description', 'adresse', 'price', 'surface_area', 'bedrooms', 'bathrooms', 'has_parking', 'quarter_id', 'type_id'];
                foreach ($draftNullable as $field) {
                    if (!in_array('nullable', $rules[$field], true)) {
                        $rules[$field][] = 'nullable';
                    }
                }
            }

            return $rules;
        }
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return [
                'is_draft' => ['sometimes', 'boolean'],
                'title' => ['sometimes', 'string', 'max:255'],
                'transaction_type' => ['nullable', 'string', 'in:location,vente'],
                'slug' => [
                    'sometimes',
                    'string',
                    'max:255',
                    Rule::unique('ad', 'slug')->ignore($this->route('ad')),
                ],
                'description' => ['sometimes', 'string'],
                'adresse' => ['sometimes', 'string', 'max:255'],
                'price' => ['sometimes', 'numeric', 'min:0'],
                'price_period' => ['sometimes', 'nullable', 'string', 'in:mois,jour'],
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

                // Premium lease conditions
                'deposit_amount' => ['nullable', 'string', 'max:50'],
                'minimum_lease_duration' => ['nullable', 'string', 'max:50'],

                // Charges
                'charges_forfaitaires' => ['nullable', 'boolean'],
                'charges_montant_forfait' => ['nullable', 'numeric', 'min:0'],
                'charges_eau' => ['nullable', 'numeric', 'min:0'],
                'charges_electricite' => ['nullable', 'numeric', 'min:0'],
                'charges_autres' => ['nullable', 'string', 'max:500'],

                // Proximity distances (metres)
                'distance_main_road_m' => ['nullable', 'integer', 'min:0', 'max:99999'],
                'distance_shops_m' => ['nullable', 'integer', 'min:0', 'max:99999'],
                'distance_transport_m' => ['nullable', 'integer', 'min:0', 'max:99999'],
                'distance_school_m' => ['nullable', 'integer', 'min:0', 'max:99999'],
                'distance_hospital_m' => ['nullable', 'integer', 'min:0', 'max:99999'],

                // Property condition PDF
                'property_condition' => $this->propertyConditionPdfRules(),

                // Boost request
                'is_boost_requested' => ['nullable', 'boolean'],

                // Images, plusieurs formats possibles
                'images' => 'sometimes|array|max:10',
                'images.*' => $this->imageFileRules(false),

                'images_to_delete' => 'sometimes|array',
                'images_to_delete.*' => 'exists:media,id',

                // Alias populaires (acceptation de variations courantes)
                'image' => $this->imageFileRules(true),
                'photos' => 'sometimes|array|max:10',
                'photos.*' => $this->imageFileRules(false),

                // Support pour images[0], images[1], etc.
                'images.0' => $this->imageFileRules(true),
                'images.1' => $this->imageFileRules(true),
                'images.2' => $this->imageFileRules(true),
                'images.3' => $this->imageFileRules(true),
                'images.4' => $this->imageFileRules(true),
                'images.5' => $this->imageFileRules(true),
                'images.6' => $this->imageFileRules(true),
                'images.7' => $this->imageFileRules(true),
                'images.8' => $this->imageFileRules(true),
                'images.9' => $this->imageFileRules(true),
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
