<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ad;

use Illuminate\Foundation\Http\FormRequest;

final class BulkDeleteAdsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'uuid'],
        ];
    }
}
