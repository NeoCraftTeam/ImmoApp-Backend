<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an ad-boost request — the boost pack to purchase with credits.
 */
class BoostAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'boost_pack_id' => ['required', 'uuid', 'exists:boost_packs,id'],
        ];
    }
}
