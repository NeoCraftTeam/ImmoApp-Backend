<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize email before validation.
     */
    #[\Override]
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => mb_strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'login_context' => ['sometimes', 'string', 'in:owner,client'],
            // Cloudflare Turnstile token. Optional: validated only when the
            // service is configured (TURNSTILE_SECRET_KEY set). Server-side
            // verification happens in LoginService.
            'turnstile_token' => ['nullable', 'string', 'max:2048'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'email.required' => 'Le mail est requis',
            'email.email' => 'Le mail n\'est pas valide',
            'password.required' => 'Le mot de passe  est requis',
            'password.string' => 'Le mot de passe n\'est pas valide',
        ];
    }
}
