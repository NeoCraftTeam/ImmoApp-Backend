<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UnlockedAdRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ad_id' => ['required', 'exists:ad,id'],
            'user_id' => ['required', 'exists:users,id'],
            'payment_id' => ['required', 'exists:payments'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'ad_id.required' => 'L\'annonce est obligatoire.',
            'ad_id.exists' => "L'annonce sélectionnée n'existe pas.",
            'user_id.required' => "L'utilisateur est obligatoire.",
            'user_id.exists' => "L'utilisateur sélectionné n'existe pas.",
            'payment_id.required' => 'Le paiement est obligatoire.',
            'payment_id.exists' => "Le paiement sélectionné n'existe pas.",
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
