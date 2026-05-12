<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GenerateLeaseContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tenant_name' => ['required', 'string', 'max:255'],
            'tenant_phone' => ['required', 'string', 'max:50'],
            'tenant_email' => ['nullable', 'email', 'max:255'],
            'tenant_id_number' => ['nullable', 'string', 'max:100'],
            'unit_reference' => ['nullable', 'string', 'max:100'],
            'lease_start' => ['required', 'date', 'after_or_equal:today'],
            'lease_duration_months' => ['required', 'integer', 'min:1', 'max:120'],
            'monthly_rent' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'special_conditions' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'tenant_name.required' => 'Le nom du locataire est requis.',
            'tenant_phone.required' => 'Le téléphone du locataire est requis.',
            'tenant_email.email' => 'L\'adresse email est invalide.',
            'lease_start.required' => 'La date de début du bail est requise.',
            'lease_start.after_or_equal' => 'La date de début doit être aujourd\'hui ou ultérieure.',
            'lease_duration_months.required' => 'La durée du bail est requise.',
            'lease_duration_months.min' => 'La durée minimale est de 1 mois.',
        ];
    }
}
