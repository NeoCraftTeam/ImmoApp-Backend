<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class MfaChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `code` stays nullable: `method=email` with no code is the "send me a code"
     * call, answered with 202.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mfa_token' => ['required', 'string', 'size:64'],
            'method' => ['nullable', 'string', 'in:totp,email,recovery'],
            'code' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'mfa_token.required' => 'La session de vérification est manquante. Reconnectez-vous.',
            'mfa_token.size' => 'La session de vérification est invalide. Reconnectez-vous.',
        ];
    }
}
