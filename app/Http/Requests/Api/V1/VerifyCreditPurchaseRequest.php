<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\PaymentTransactionLookup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a credit-purchase verification request — accepts the KeyHome
 * `KH-*` transaction reference and/or the gateway reference; both optional
 * (falls back to the user's latest credit purchase when neither is supplied).
 */
class VerifyCreditPurchaseRequest extends FormRequest
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
                'nullable',
                'string',
                'max:255',
                'regex:/^KH-[A-Z0-9]{6,32}$/i',
            ],
            'reference' => [
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
}
