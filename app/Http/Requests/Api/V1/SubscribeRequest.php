<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
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
            'plan_id' => ['required', 'uuid', 'exists:subscription_plans,id'],
            'billing_period' => ['required', 'in:monthly,yearly'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Le plan d\'abonnement est requis.',
            'plan_id.uuid' => 'L\'identifiant du plan est invalide.',
            'plan_id.exists' => 'Le plan sélectionné n\'existe pas.',
            'billing_period.required' => 'La période de facturation est requise.',
            'billing_period.in' => 'La période doit être "monthly" ou "yearly".',
        ];
    }
}
