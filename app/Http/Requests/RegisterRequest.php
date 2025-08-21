<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'email' => 'required|email:|max:255|unique:user,email',
            'phone_number' => 'required|string|regex:/^[+]?[0-9\s\-\(\)]{10,15}$/',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'password_confirmation' => 'required|same:password',
            'role' => 'nullable|string|in:customer,admin,agent',
            'type' => 'nullable|string|max:50',
            'city_id' => 'nullable|integer|exists:city,id',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048|dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
        ];
    }

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
            'password_confirmation.same' => 'La confirmation doit être identique au mot de passe.',
            'avatar.image' => 'Le fichier doit être une image.',
            'avatar.mimes' => 'L\'avatar doit être au format JPEG, JPG, PNG ou WebP.',
            'avatar.max' => 'L\'avatar ne peut pas dépasser 2MB.',
            'avatar.dimensions' => 'L\'image doit faire entre 100x100 et 2000x2000 pixels.',
            'role.in' => 'Le rôle sélectionné n\'est pas valide.',
            'city_id.exists' => 'La ville sélectionnée n\'existe pas.',
        ];
    }
}
