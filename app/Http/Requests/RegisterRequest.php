<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'firstname' => 'required|string|max:50|regex:/^[a-zA-ZÀ-ÿ\s]+$/',
            'lastname' => 'required|string|max:50|regex:/^[a-zA-ZÀ-ÿ\s]+$/',
            'email' => 'required|email:|max:255|unique:users,email',
            'phone_number' => 'required|string|regex:/^[+]?[0-9\s\-\(\)]{10,15}$/',
            'password' => [
                'required',
                'confirmed:confirm_password',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'confirm_password' => 'required|same:password',
            'role' => 'nullable|string|in:customer,agent',
            'type' => 'nullable|string|max:50',
            'city_id' => 'nullable|string|exists:city,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048|dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'firstname.required' => 'Le prénom est obligatoire.',
            'firstname.regex' => 'Le prénom ne peut contenir que des lettres et espaces.',
            'lastname.required' => 'Le nom est obligatoire.',
            'lastname.regex' => 'Le nom ne peut contenir que des lettres et espaces.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'phone_number.required' => 'Le numéro de téléphone est obligatoire.',
            'phone_number.regex' => 'Le numéro de téléphone n\'est pas valide.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'confirm_password.same' => 'La confirmation doit être identique au mot de passe.',
            'avatar.image' => 'Le fichier doit être une image.',
            'avatar.mimes' => 'L\'avatar doit être au format JPEG, JPG, PNG ou WebP.',
            'avatar.max' => 'L\'avatar ne peut pas dépasser 2MB.',
            'avatar.dimensions' => 'L\'image doit faire entre 100x100 et 2000x2000 pixels.',
            'role.in' => 'Le rôle sélectionné n\'est pas valide.',
            'city_id.exists' => 'La ville sélectionnée n\'existe pas.',
        ];
    }
}
