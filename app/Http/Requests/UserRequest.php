<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^\+?[0-9]{7,20}$/'],
            'email' => ['required', 'email', 'max:255', Rule::unique('user', 'email')->ignore($this->user()?->id)], // if the user is connected, ignore their own email
            'password' => ['required', 'string', 'min:8'],
            'avatar' => ['required', 'image', 'max:2048'],
            'role' => ['required', 'string'],
            'type' => ['nullable', 'string'],
            'city_id' => ['required', 'integer', 'exists:city,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'firstname.required' => 'Le prénom est obligatoire.',
            'lastname.required' => 'Le nom est obligatoire.',
            'phone_number.required' => 'Le numéro de téléphone est obligatoire.',
            'email.required' => 'L\'email est obligatoire.',
            'email.email' => 'L\'email doit être une adresse email valide.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit comporter au moins 8 caractères.',
            'password.confirmed' => 'Le mot de passe et sa confirmation ne correspondent pas.',
            'avatar.required' => "L'avatar est obligatoire.",
            'role.required' => "Le rôle est obligatoire.",
            'city_id.required' => "La ville est obligatoire.",
        ];
    }
}
