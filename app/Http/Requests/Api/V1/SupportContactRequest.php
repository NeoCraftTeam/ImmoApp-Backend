<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SupportContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'Votre nom est requis.',
            'name.min' => 'Votre nom doit contenir au moins 2 caractères.',
            'email.required' => "L'adresse email est requise.",
            'email.email' => "L'adresse email n'est pas valide.",
            'subject.required' => 'Le sujet est requis.',
            'message.required' => 'Le message est requis.',
            'message.min' => 'Votre message doit contenir au moins 10 caractères.',
        ];
    }
}
