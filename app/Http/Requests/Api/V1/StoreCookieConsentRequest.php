<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCookieConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'analytics' => ['required', 'boolean'],
            'marketing' => ['required', 'boolean'],
            'policy_version' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
