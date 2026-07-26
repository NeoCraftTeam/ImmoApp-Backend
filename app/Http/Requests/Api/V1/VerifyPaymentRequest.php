<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\PaymentTransactionLookup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a payment-verification request — accepts the KeyHome `KH-*`
 * transaction reference and/or the gateway reference (e.g. Kpay `pay_*`, `KPAY-*`).
 */
class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tx_ref' => [
                'required_without:reference',
                'nullable',
                'string',
                'max:255',
                'regex:/^KH-[A-Z0-9]{6,32}$/i',
            ],
            'reference' => [
                'required_without:tx_ref',
                'nullable',
                'string',
                'max:255',
                'regex:'.PaymentTransactionLookup::gatewayReferenceValidationPattern(),
            ],
            'gateway_redirect_status' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'tx_ref.required_without' => 'La référence de transaction KeyHome ou la référence Kpay est requise.',
            'tx_ref.string' => 'La référence de transaction doit être une chaîne.',
            'tx_ref.regex' => 'La référence KeyHome est invalide.',
            'reference.required_without' => 'La référence de transaction KeyHome ou la référence Kpay est requise.',
            'reference.string' => 'La référence Kpay doit être une chaîne.',
            'reference.regex' => 'La référence Kpay est invalide.',
        ];
    }
}
