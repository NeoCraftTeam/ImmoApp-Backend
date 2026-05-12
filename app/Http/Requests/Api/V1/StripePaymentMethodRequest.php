<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the `{paymentMethod}` path parameter used by
 * the saved-card endpoints (detach + set-default).
 *
 * Stripe payment method ids always start with `pm_` followed by base62
 * characters. We pin the format so a malicious client cannot smuggle
 * other Stripe object ids (e.g. `cus_xxx`, `pi_xxx`) into the routes.
 */
class StripePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'paymentMethod' => ['required', 'string', 'regex:/^pm_[A-Za-z0-9_]+$/', 'max:255'],
        ];
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'paymentMethod' => (string) $this->route('paymentMethod'),
        ]);
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'paymentMethod.regex' => 'L\'identifiant de carte enregistrée est invalide.',
            'paymentMethod.required' => 'L\'identifiant de carte enregistrée est requis.',
        ];
    }
}
