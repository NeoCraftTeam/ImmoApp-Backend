<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ApplyPromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'payment_type' => ['required', 'string', 'in:subscription,credit'],
            'original_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'code.required' => 'Le code promo est requis.',
            'payment_type.required' => 'Le type de paiement est requis.',
            'payment_type.in' => 'Le type de paiement doit être subscription ou credit.',
            'original_amount.required' => 'Le montant original est requis.',
            'original_amount.numeric' => 'Le montant doit être un nombre.',
        ];
    }
}
