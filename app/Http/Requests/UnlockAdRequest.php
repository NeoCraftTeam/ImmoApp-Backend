<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UnlockAdRequest extends FormRequest
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
            'ad_id' => ['required', 'uuid', 'exists:ad,id'],
            // Apple Pay / Google Pay are outcomes resolved from Stripe's
            // `payment_method_details.card.wallet`, never client-selected: the
            // caller asks for `card` and Stripe reports the wallet afterwards.
            // Accepting them here would let a client pin a method the
            // initiation flow does not route (only `card` enters the Stripe
            // flow — see PaymentService::initiate()).
            'payment_method' => [
                'required',
                'string',
                Rule::enum(PaymentMethod::class)->except([
                    PaymentMethod::ApplePay,
                    PaymentMethod::GooglePay,
                ]),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'ad_id.exists' => "L'annonce spécifiée est introuvable.",
            'payment_method.enum' => 'La méthode de paiement choisie est invalide.',
        ];
    }
}
