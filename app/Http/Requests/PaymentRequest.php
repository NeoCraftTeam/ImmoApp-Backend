<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required'],
            'user_id' => ['required', 'exists:users,id'],
            'ad_id' => ['required', 'exists:ad,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Le montant est obligatoire.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'user_id.required' => "L'utilisateur est obligatoire.",
            'user_id.exists' => "L'utilisateur sélectionné n'existe pas.",
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
