<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
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
