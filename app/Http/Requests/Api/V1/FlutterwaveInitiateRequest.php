<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class FlutterwaveInitiateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:subscription,credit'],
            'payment_method' => ['nullable', 'string', 'in:mobile_money,orange_money,flutterwave,card'],
            'phone_number' => ['nullable', 'string', 'regex:/^\\+?[0-9\\s\\-]{7,20}$/'],
            'agency_id' => ['required_if:type,subscription', 'nullable', 'uuid', 'exists:agency,id'],
            'plan_id' => [
                'required_if:type,subscription',
                'required_if:type,credit',
                'nullable',
                'uuid',
            ],
            'period' => ['required_if:type,subscription', 'nullable', 'string', 'in:monthly,yearly'],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'type.required' => 'Le type de paiement est requis.',
            'type.in' => 'Le type doit être subscription ou credit.',
            'phone_number.regex' => 'Le format du numéro de téléphone est invalide.',
            'agency_id.required_if' => 'L\'agence est requise pour un abonnement.',
            'agency_id.exists' => 'L\'agence spécifiée est introuvable.',
            'plan_id.required_if' => 'Le plan est requis pour ce type de paiement.',
            'period.required_if' => 'La période est requise pour un abonnement.',
            'period.in' => 'La période doit être monthly ou yearly.',
        ];
    }
}
