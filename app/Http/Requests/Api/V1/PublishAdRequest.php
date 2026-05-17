<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\AdStatus;
use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates that a draft ad has all required fields before it can be published.
 *
 * The validation is performed against the persisted ad record (not the request
 * body, which is empty for this endpoint) so we lock the record inside
 * `withValidator` to detect and report every missing field at once.
 */
final class PublishAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * No body fields are sent to the publish endpoint itself; the ad is
     * identified via the route model binding on {ad}.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * After the (empty) field rules pass, validate the persisted ad record.
     *
     * Checks that the ad is still a DRAFT and that all mandatory listing
     * fields are non-null/non-empty. Reports all missing fields at once
     * using French field labels so the mobile client can surface them directly.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var Ad|null $ad */
            $ad = $this->route('ad');

            if (!$ad instanceof Ad) {
                $v->errors()->add('ad', 'Annonce introuvable.');

                return;
            }

            if ($ad->status !== AdStatus::DRAFT) {
                $v->errors()->add('status', 'Seuls les brouillons peuvent être publiés.');

                return;
            }

            /** @var array<string, string> $requiredFields Field attribute => French label */
            $requiredFields = [
                'title' => 'titre',
                'description' => 'description',
                'adresse' => 'adresse',
                'price' => 'prix',
                'surface_area' => 'surface',
                'quarter_id' => 'quartier',
                'type_id' => 'type',
            ];

            $missing = [];

            foreach ($requiredFields as $attribute => $label) {
                if ($ad->getAttribute($attribute) === null || $ad->getAttribute($attribute) === '') {
                    $missing[] = $label;
                    $v->errors()->add($attribute, "Le champ \"{$label}\" est obligatoire pour publier l'annonce.");
                }
            }

            if ($missing !== []) {
                // Also surface a top-level summary so clients can show a single toast.
                $v->errors()->add(
                    'missing_fields',
                    'Veuillez compléter les champs obligatoires avant de publier : '.implode(', ', $missing).'.'
                );
            }
        });
    }
}
