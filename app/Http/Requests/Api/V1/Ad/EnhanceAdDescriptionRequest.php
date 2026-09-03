<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation rules shared by the JSON and SSE enhance endpoints: the raw
 * description plus optional form facts the model can weave in without inventing.
 * `attributes` stays lenient (array only) so the mobile client's `string[]`
 * of feature labels is accepted as-is.
 */
final class EnhanceAdDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:10000'],
            'type' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'quarter' => ['nullable', 'string', 'max:100'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'surface' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'transaction_type' => ['nullable', 'string', 'max:50'],
            'attributes' => ['nullable', 'array'],
        ];
    }
}
