<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a payment-cancellation request — the KeyHome `tx_ref` of the
 * pending payment to cancel.
 */
class CancelPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tx_ref' => ['required', 'string'],
        ];
    }
}
