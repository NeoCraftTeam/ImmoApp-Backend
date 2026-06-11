<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRentPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // We store the period as the first day of the rental month so
            // partial payments for the same month aggregate cleanly. The
            // frontend can pass any date inside the target month; the
            // controller normalises it.
            'period_month' => ['required', 'date'],
            // XAF is a no-decimal currency — keep it as an integer.
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:cash,mobile_money,bank_transfer,other'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
