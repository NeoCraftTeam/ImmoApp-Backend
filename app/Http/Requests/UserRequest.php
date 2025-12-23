<?php

namespace App\Http\Requests;

use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Http\Requests\TransformsGeojsonGeometry;
use Clickbar\Magellan\Rules\GeometryGeojsonRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    use TransformsGeojsonGeometry;

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
        if ($this->isMethod('post')) {
            return [
                'firstname' => ['required', 'string', 'max:255'],
                'lastname' => ['required', 'string', 'max:255'],
                'phone_number' => ['required', 'string', 'regex:/^\+?[0-9]{7,20}$/'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)], // if the user is connected, ignore their own email
                'password' => ['required', 'string', 'min:8', 'confirmed:confirm_password'],
                'location' => ['sometimes', new GeometryGeojsonRule([Point::class])],
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'role' => ['required', 'string'],
                'type' => ['nullable', 'string'],
                'city_id' => ['sometimes', 'uuid', 'exists:city,id'],
            ];
        }
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return [
                'firstname' => ['sometimes', 'string', 'max:255'],
                'lastname' => ['sometimes', 'string', 'max:255'],
                'phone_number' => ['sometimes', 'string', 'regex:/^\+?[0-9]{7,20}$/'],
                'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)], // if the user is connected, ignore their own email
                'password' => ['sometimes', 'string', 'min:8'],
                'role' => ['sometimes', 'string'],
                'type' => ['nullable', 'string'],
                'city_id' => ['sometimes', 'uuid', 'exists:city,id'],
                'location' => ['sometimes', new GeometryGeojsonRule([Point::class])],
                'latitude' => 'sometimes|nullable|numeric|between:-90,90',
                'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            ];
        }

        return [];
    }

    public function geometries(): array
    {
        return ['location'];
    }

    #[\Override]
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
            'city_id.required' => 'La ville est obligatoire.',
        ];
    }
}
