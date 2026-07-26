<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateRentPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period_month' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'payment_method' => ['sometimes', 'string', 'in:cash,mobile_money,bank_transfer,other'],
            'received_at' => ['sometimes', 'date', 'before_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
