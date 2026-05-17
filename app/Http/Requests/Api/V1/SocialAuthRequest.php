<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the OAuth token payload sent by mobile/SPA clients.
 *
 * The `role` field is accepted but intentionally ignored during user creation.
 * OAuth users always receive the CUSTOMER role. See {@see SocialAuthController::findOrCreateUser()}.
 */
final class SocialAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for the OAuth authenticate endpoint.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'id_token' => ['nullable', 'string'],
            'role' => ['nullable', 'string', 'in:customer,agent'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'token.required' => 'Le token OAuth est obligatoire.',
            'token.string' => 'Le token OAuth doit être une chaîne de caractères.',
            'role.in' => 'Le rôle doit être customer ou agent.',
        ];
    }
}
