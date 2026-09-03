<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation rules for saving an edit-draft payload. `attributes.*`
 * mirrors `AutosaveAdRequest` (exists + active) so an owner can't stash
 * an invalid slug in `draft_payload` to surface only on apply.
 */
final class SaveAdEditDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:500'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price_period' => ['sometimes', 'nullable', 'string', 'in:mois,jour'],
            'surface_area' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'bedrooms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'bathrooms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'has_parking' => ['sometimes', 'nullable', 'boolean'],
            'deposit_amount' => ['sometimes', 'nullable', 'string', 'max:50'],
            'minimum_lease_duration' => ['sometimes', 'nullable', 'string', 'max:50'],
            'charges_forfaitaires' => ['sometimes', 'nullable', 'boolean'],
            'charges_montant_forfait' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'charges_eau' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'charges_electricite' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'charges_autres' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'quarter_id' => ['sometimes', 'nullable', 'uuid', 'exists:quarter,id'],
            'type_id' => ['sometimes', 'nullable', 'uuid', 'exists:ad_type,id'],
            'transaction_type' => ['sometimes', 'nullable', 'string', 'in:location,vente'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'attributes' => ['sometimes', 'nullable', 'array', 'max:50'],
            'attributes.*' => [
                'string',
                Rule::exists('property_attributes', 'slug')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
        ];
    }
}
