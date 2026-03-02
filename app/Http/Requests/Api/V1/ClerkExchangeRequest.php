<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ClerkExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone_number' => ['sometimes', 'nullable', 'string', 'regex:/^[+]?[0-9\s\-\(\)]{8,15}$/'],
            'city_id' => ['sometimes', 'nullable', 'string', 'exists:city,id'],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'Le numéro de téléphone n\'est pas valide.',
            'city_id.exists' => 'La ville sélectionnée n\'existe pas.',
        ];
    }

    #[\Override]
    protected function passedValidation(): void
    {
        if (!$this->bearerToken()) {
            throw new HttpResponseException(
                response()->json(['message' => 'Token Clerk manquant dans le header Authorization.'], 401)
            );
        }
    }
}
