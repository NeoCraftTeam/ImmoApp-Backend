<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Http\Requests\TransformsGeojsonGeometry;
use Clickbar\Magellan\Rules\GeometryGeojsonRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UserRequest extends FormRequest
{
    use TransformsGeojsonGeometry;

    #[\Override]
    protected function prepareForValidation(): void
    {
        if (!$this->has('phone_number')) {
            return;
        }

        $raw = $this->input('phone_number');
        if (!is_string($raw)) {
            return;
        }

        $trimmed = trim($raw);
        $hasPlus = str_starts_with($trimmed, '+');
        $digitsOnly = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digitsOnly === '' || strlen($digitsOnly) < 7) {
            $this->getInputSource()->remove('phone_number');

            return;
        }

        $normalized = ($hasPlus ? '+' : '').$digitsOnly;

        $this->merge([
            'phone_number' => $normalized,
        ]);
    }

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
                'role' => ['required', 'string', Rule::in(['customer', 'agent'])],
                'type' => ['nullable', 'string', Rule::in(['individual', 'agency'])],
                'city_id' => ['sometimes', 'uuid', 'exists:city,id'],
                'avatar' => ['sometimes', 'nullable', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:max_width=2000,max_height=2000'],
            ];
        }
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return [
                'firstname' => ['sometimes', 'string', 'max:255'],
                'lastname' => ['sometimes', 'string', 'max:255'],
                // Owner public bio supports lightweight Markdown (**bold**, *italic*,
                // headings, lists). Plain-text length cap raised to 2 000 chars; HTML
                // rendering happens on the read side via a sanitizing markdown pass.
                'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'phone_number' => ['sometimes', 'string', 'regex:/^\+?[0-9]{7,20}$/'],
                'phone_is_whatsapp' => ['sometimes', 'boolean'],
                'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
                'password' => ['sometimes', 'string', 'min:8'],
                'city_id' => ['sometimes', 'uuid', 'exists:city,id'],
                'avatar' => ['sometimes', 'nullable', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:max_width=2000,max_height=2000'],
                'location' => ['sometimes', new GeometryGeojsonRule([Point::class])],
                'latitude' => 'sometimes|nullable|numeric|between:-90,90',
                'longitude' => 'sometimes|nullable|numeric|between:-180,180',
                // P0-7 Fix: Only admins can update role/type to prevent privilege escalation
                ...($this->user()?->isAdmin() ? [
                    'role' => ['sometimes', 'string'],
                    'type' => ['nullable', 'string'],
                ] : []),
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
            'phone_number.regex' => 'Le numéro de téléphone doit contenir entre 7 et 20 chiffres (indicatif inclus).',
            'avatar.image' => 'La photo de profil doit être une image valide.',
            'avatar.max' => 'La photo de profil ne doit pas dépasser 5 Mo.',
            'avatar.mimes' => 'Formats acceptés : JPEG, PNG, GIF, WebP.',
            'avatar.dimensions' => 'La photo de profil ne doit pas dépasser 2000×2000 pixels.',
        ];
    }
}
